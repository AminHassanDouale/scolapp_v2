<?php

namespace App\Console\Commands;

use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Notifications\AttendanceAlertNotification;
use App\Notifications\InvoiceGeneratedNotification;
use App\Notifications\InvoiceOverdueNotification;
use App\Services\EnrollmentService;
use App\Services\WhatsAppService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

class TestWhatsApp extends Command
{
    protected $signature = 'whatsapp:test
        {--phone= : Phone number to send to (e.g. 25377000000)}
        {--type=ping : Test type: ping | attendance | invoice-overdue | invoice-pdf | full-enrollment}
        {--guardian= : Guardian ID to use for invoice/attendance tests}
        {--invoice= : Invoice ID to use for invoice-pdf test}
        {--enrollment= : Enrollment ID to use for full-enrollment test}
        {--sync : Process the queue synchronously after dispatching}';

    protected $description = 'Send test WhatsApp/email notifications to verify all channels';

    public function handle(WhatsAppService $whatsapp): int
    {
        $type = $this->option('type');

        return match ($type) {
            'ping'             => $this->doPing($whatsapp),
            'attendance'       => $this->doAttendance(),
            'invoice-overdue'  => $this->doInvoiceOverdue(),
            'invoice-pdf'      => $this->doInvoicePdf(),
            'full-enrollment'  => $this->doFullEnrollment(),
            default            => $this->badType(),
        };
    }

    // ── ping ─────────────────────────────────────────────────────────────────

    private function doPing(WhatsAppService $whatsapp): int
    {
        $phone = $this->option('phone');
        if (! $phone) {
            $this->error('--phone is required for ping test.');
            return self::FAILURE;
        }

        $this->info("Sending ping to {$phone}...");
        $ok = $whatsapp->sendMessage($phone, implode("\n", [
            '✅ *ScolApp — Test WhatsApp*',
            '',
            'Ce message confirme que l\'intégration WhatsApp fonctionne correctement.',
            '_Instance : ' . config('services.ultramsg.instance_id') . '_',
        ]));

        $ok ? $this->info('✓ Message sent.') : $this->error('✗ Failed — check logs.');
        return $ok ? self::SUCCESS : self::FAILURE;
    }

    // ── attendance ────────────────────────────────────────────────────────────

    private function doAttendance(): int
    {
        $guardian = $this->resolveGuardian();
        if (! $guardian) return self::FAILURE;

        $student = $guardian->students()->first();
        if (! $student) {
            $this->error('Guardian has no linked students.');
            return self::FAILURE;
        }

        $this->info("→ AttendanceAlertNotification");
        $this->line("  Guardian : {$guardian->full_name}");
        $this->line("  Student  : {$student->full_name}");
        $this->line("  Phone    : " . ($guardian->whatsapp_number ?? $guardian->phone));

        Notification::send($guardian, new AttendanceAlertNotification($student, 'repeated_absence', 3));
        $this->info('✓ Dispatched (email + WhatsApp).');
        $this->processQueue();
        return self::SUCCESS;
    }

    // ── invoice-overdue ───────────────────────────────────────────────────────

    private function doInvoiceOverdue(): int
    {
        $guardian = $this->resolveGuardian();
        if (! $guardian) return self::FAILURE;

        $student = $guardian->students()->first();
        $invoice = $student ? Invoice::where('student_id', $student->id)->latest()->first() : null;

        if (! $invoice) {
            $this->error('No invoice found for this guardian\'s student.');
            return self::FAILURE;
        }

        $this->info("→ InvoiceOverdueNotification");
        $this->line("  Guardian : {$guardian->full_name}");
        $this->line("  Invoice  : {$invoice->reference} — {$invoice->balance_due} DJF");
        $this->line("  Phone    : " . ($guardian->whatsapp_number ?? $guardian->phone));

        Notification::send($guardian, new InvoiceOverdueNotification($invoice));
        $this->info('✓ Dispatched (email + WhatsApp).');
        $this->processQueue();
        return self::SUCCESS;
    }

    // ── invoice-pdf ───────────────────────────────────────────────────────────

    private function doInvoicePdf(): int
    {
        $guardian  = $this->resolveGuardian();
        if (! $guardian) return self::FAILURE;

        $invoiceId = $this->option('invoice');
        $invoice   = $invoiceId
            ? Invoice::find($invoiceId)
            : Invoice::whereHas('student.guardians', fn($q) => $q->where('guardians.id', $guardian->id))->latest()->first();

        if (! $invoice) {
            $this->error('No invoice found. Use --invoice=ID.');
            return self::FAILURE;
        }

        $this->info("→ InvoiceGeneratedNotification (text + PDF attachment)");
        $this->line("  Guardian : {$guardian->full_name}");
        $this->line("  Invoice  : {$invoice->reference}");
        $this->line("  Phone    : " . ($guardian->whatsapp_number ?? $guardian->phone));

        Notification::send($guardian, new InvoiceGeneratedNotification($invoice, $guardian));
        $this->info('✓ Dispatched.');
        $this->processQueue();
        return self::SUCCESS;
    }

    // ── full-enrollment ───────────────────────────────────────────────────────
    // Simulates the complete inscription flow:
    //   For each guardian of the enrollment's student:
    //     • Email  — one InvoiceGeneratedMail per invoice (registration + installments)
    //     • WhatsApp — InvoiceGeneratedNotification (text + PDF) per invoice

    private function doFullEnrollment(): int
    {
        $enrollmentId = $this->option('enrollment');

        if ($enrollmentId) {
            $enrollment = Enrollment::with('student.guardians', 'invoices')->find($enrollmentId);
        } else {
            // Pick most recent enrollment that has invoices
            $enrollment = Enrollment::with('student.guardians', 'invoices')
                ->whereHas('invoices')
                ->latest()
                ->first();
        }

        if (! $enrollment) {
            $this->error('No enrollment with invoices found. Use --enrollment=ID.');
            return self::FAILURE;
        }

        $student  = $enrollment->student;
        $invoices = $enrollment->invoices;
        $guardians = $student->guardians;

        $this->info("→ Full enrollment notification test");
        $this->line("  Enrollment : #{$enrollment->id}");
        $this->line("  Student    : {$student->full_name}");
        $this->line("  Invoices   : {$invoices->count()} (" . $invoices->pluck('reference')->join(', ') . ')');
        $this->line("  Guardians  : {$guardians->count()}");
        $this->newLine();

        $emailCount = 0;
        $waCount    = 0;

        foreach ($guardians as $guardian) {
            $phone = $guardian->whatsapp_number ?? $guardian->phone ?? null;
            $email = $guardian->email ?? null;

            $this->line("  ┌ Guardian #{$guardian->id}: {$guardian->full_name}");
            $this->line("  │  Email  : " . ($email  ? $email  : '(none)'));
            $this->line("  │  Phone  : " . ($phone  ? $phone  : '(none)'));

            foreach ($invoices as $invoice) {
                // Email
                if ($email) {
                    try {
                        Mail::queue(new \App\Mail\InvoiceGeneratedMail($invoice, $guardian));
                        $this->line("  │  ✉  Email queued  — {$invoice->reference}");
                        $emailCount++;
                    } catch (\Throwable $e) {
                        $this->warn("  │  ✗  Email failed  — {$invoice->reference}: {$e->getMessage()}");
                    }
                }

                // WhatsApp
                if ($phone) {
                    try {
                        Notification::send($guardian, new InvoiceGeneratedNotification($invoice, $guardian));
                        $this->line("  │  📱 WA queued     — {$invoice->reference}");
                        $waCount++;
                    } catch (\Throwable $e) {
                        $this->warn("  │  ✗  WA failed     — {$invoice->reference}: {$e->getMessage()}");
                    }
                }
            }
            $this->line("  └─────────────────────────────────────────");
        }

        $this->newLine();
        $this->info("✓ Dispatched: {$emailCount} email(s), {$waCount} WhatsApp notification(s).");
        $this->processQueue();
        return self::SUCCESS;
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function processQueue(): void
    {
        if (config('queue.default') === 'sync' || $this->option('sync')) {
            return; // sync driver processes immediately
        }
        if ($this->option('sync') || $this->confirm('Process the queue now? (queue:work --stop-when-empty)', true)) {
            $this->call('queue:work', ['--stop-when-empty' => true, '--tries' => 3]);
        }
    }

    private function resolveGuardian(): ?Guardian
    {
        $id = $this->option('guardian');
        if ($id) {
            $g = Guardian::with('students')->find($id);
            if (! $g) { $this->error("Guardian #{$id} not found."); return null; }
            return $g;
        }

        $g = Guardian::with('students')
            ->where(fn($q) => $q->whereNotNull('whatsapp_number')->orWhereNotNull('phone'))
            ->first();

        if (! $g) { $this->error('No guardian with a phone found. Use --guardian=ID.'); return null; }

        $this->line("Auto-selected guardian #{$g->id}: {$g->full_name} (" . ($g->whatsapp_number ?? $g->phone) . ')');
        return $g;
    }

    private function badType(): int
    {
        $this->error('Unknown --type. Use: ping | attendance | invoice-overdue | invoice-pdf | full-enrollment');
        return self::FAILURE;
    }
}
