<?php

namespace App\Console\Commands;

use App\Enums\AttendanceStatus;
use App\Enums\InvoiceStatus;
use App\Enums\ScheduledTaskType;
use App\Exports\Tasks\AttendanceSummaryExport;
use App\Exports\Tasks\FinancialSummaryExport;
use App\Exports\Tasks\InvoiceReminderExport;
use App\Exports\Tasks\OverdueAlertExport;
use App\Exports\Tasks\PaymentDueSoonExport;
use App\Models\AttendanceEntry;
use App\Models\AttendanceSession;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ScheduledTask;
use App\Models\Student;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class DispatchScheduledTasks extends Command
{
    protected $signature   = 'tasks:dispatch {--school= : Only run for this school_id}';
    protected $description = 'Dispatch all scheduled tasks that are due to run';

    public function handle(): int
    {
        $query = ScheduledTask::with(['school', 'targetClass', 'targetGrade'])
            ->where('is_active', true)
            ->where('next_run_at', '<=', now());

        if ($school = $this->option('school')) {
            $query->where('school_id', $school);
        }

        $tasks = $query->get();

        if ($tasks->isEmpty()) {
            $this->info('No tasks due.');
            return self::SUCCESS;
        }

        $this->info("Running {$tasks->count()} task(s)…");

        foreach ($tasks as $task) {
            $this->line(" → [{$task->type->label()}] {$task->name}");
            try {
                $this->runTask($task);
                $task->update([
                    'last_run_at'   => now(),
                    'next_run_at'   => $task->computeNextRunAt(),
                    'run_count'     => $task->run_count + 1,
                    'last_error'    => null,
                ]);
                $this->info("   ✓ Done");
            } catch (Throwable $e) {
                $task->update([
                    'last_run_at'   => now(),
                    'next_run_at'   => $task->computeNextRunAt(),
                    'failure_count' => $task->failure_count + 1,
                    'last_error'    => $e->getMessage(),
                ]);
                $this->error("   ✗ " . $e->getMessage());
            }
        }

        return self::SUCCESS;
    }

    // -------------------------------------------------------------------------
    // Dispatcher
    // -------------------------------------------------------------------------

    private function runTask(ScheduledTask $task): void
    {
        match ($task->type) {
            ScheduledTaskType::INVOICE_REMINDER    => $this->runInvoiceReminder($task),
            ScheduledTaskType::OVERDUE_ALERT       => $this->runOverdueAlert($task),
            ScheduledTaskType::PAYMENT_DUE_SOON    => $this->runPaymentDueSoon($task),
            ScheduledTaskType::ATTENDANCE_SUMMARY  => $this->runAttendanceSummary($task),
            ScheduledTaskType::FINANCIAL_SUMMARY   => $this->runFinancialSummary($task),
            ScheduledTaskType::CUSTOM_NOTIFICATION => $this->runCustomNotification($task),
        };
    }

    // ── Invoice Reminder ──────────────────────────────────────────────────────

    private function runInvoiceReminder(ScheduledTask $task): void
    {
        $school    = $task->school;
        $classId   = $task->target_type === 'class_guardians' ? $task->target_id : null;
        $gradeId   = $task->target_type === 'grade_guardians'  ? $task->target_id : null;

        $invoices  = Invoice::where('school_id', $task->school_id)
            ->whereNotIn('status', [InvoiceStatus::PAID->value, InvoiceStatus::CANCELLED->value])
            ->where('balance_due', '>', 0)
            ->with(['student.guardians', 'academicYear'])
            ->when($classId, fn($q) => $q->whereHas('enrollment', fn($e) => $e->where('school_class_id', $classId)))
            ->when($gradeId, fn($q) => $q->whereHas('enrollment', fn($e) => $e->where('grade_id', $gradeId)))
            ->get();

        $excelPath = $this->generateExcel(
            new InvoiceReminderExport($task->school_id, $classId, $gradeId),
            'rappel-paiements'
        );

        foreach ($invoices as $invoice) {
            foreach ($invoice->student?->guardians?->whereNotNull('email') ?? [] as $guardian) {
                Mail::send('emails.tasks.invoice-reminder', compact('invoice', 'school', 'guardian'), function ($msg) use ($guardian, $invoice, $task, $excelPath) {
                    $msg->to($guardian->email)
                        ->subject($task->meta['custom_subject'] ?? "Rappel de paiement — {$invoice->reference}");
                    if ($excelPath) {
                        $msg->attach($excelPath, [
                            'as'   => 'rappel-paiements-' . now()->format('Ymd') . '.xlsx',
                            'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ]);
                    }
                });
            }
        }

        $this->sendToExtraRecipients($task, [], $excelPath, 'rappel-paiements');
        $this->cleanupExcel($excelPath);
    }

    // ── Overdue Alert ─────────────────────────────────────────────────────────

    private function runOverdueAlert(ScheduledTask $task): void
    {
        $school  = $task->school;
        $classId = $task->target_type === 'class_guardians' ? $task->target_id : null;

        $invoices = Invoice::where('school_id', $task->school_id)
            ->overdue()
            ->with(['student.guardians', 'academicYear'])
            ->when($classId, fn($q) => $q->whereHas('enrollment', fn($e) => $e->where('school_class_id', $classId)))
            ->get();

        $excelPath = $this->generateExcel(
            new OverdueAlertExport($task->school_id, $classId),
            'alertes-retards'
        );

        foreach ($invoices as $invoice) {
            foreach ($invoice->student?->guardians?->whereNotNull('email') ?? [] as $guardian) {
                Mail::send('emails.tasks.overdue-alert', compact('invoice', 'school', 'guardian'), function ($msg) use ($guardian, $invoice, $task, $excelPath) {
                    $msg->to($guardian->email)
                        ->subject($task->meta['custom_subject'] ?? "⚠ Facture en retard — {$invoice->reference}");
                    if ($excelPath) {
                        $msg->attach($excelPath, [
                            'as'   => 'alertes-retards-' . now()->format('Ymd') . '.xlsx',
                            'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ]);
                    }
                });
            }
        }

        $this->sendToExtraRecipients($task, [], $excelPath, 'alertes-retards');
        $this->cleanupExcel($excelPath);
    }

    // ── Payment Due Soon ──────────────────────────────────────────────────────

    private function runPaymentDueSoon(ScheduledTask $task): void
    {
        $school     = $task->school;
        $daysBefore = $task->meta['days_before'] ?? 7;

        $invoices = Invoice::where('school_id', $task->school_id)
            ->whereNotIn('status', [InvoiceStatus::PAID->value, InvoiceStatus::CANCELLED->value])
            ->whereBetween('due_date', [now()->toDateString(), now()->addDays($daysBefore)->toDateString()])
            ->with(['student.guardians', 'academicYear'])
            ->get();

        $excelPath = $this->generateExcel(
            new PaymentDueSoonExport($task->school_id, $daysBefore),
            'echeances-proches'
        );

        foreach ($invoices as $invoice) {
            foreach ($invoice->student?->guardians?->whereNotNull('email') ?? [] as $guardian) {
                Mail::send('emails.tasks.payment-due-soon', compact('invoice', 'school', 'guardian', 'daysBefore'), function ($msg) use ($guardian, $invoice, $task, $excelPath) {
                    $msg->to($guardian->email)
                        ->subject($task->meta['custom_subject'] ?? "Échéance de paiement proche — {$invoice->reference}");
                    if ($excelPath) {
                        $msg->attach($excelPath, [
                            'as'   => 'echeances-proches-' . now()->format('Ymd') . '.xlsx',
                            'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ]);
                    }
                });
            }
        }

        $this->sendToExtraRecipients($task, [], $excelPath, 'echeances-proches');
        $this->cleanupExcel($excelPath);
    }

    // ── Attendance Summary ────────────────────────────────────────────────────

    private function runAttendanceSummary(ScheduledTask $task): void
    {
        $school  = $task->school;
        $classId = $task->target_type === 'class_guardians' ? $task->target_id : null;

        $sessionIds = AttendanceSession::where('school_id', $task->school_id)
            ->whereBetween('session_date', [now()->subWeek()->toDateString(), now()->toDateString()])
            ->when($classId, fn($q) => $q->where('school_class_id', $classId))
            ->pluck('id');

        $total   = AttendanceEntry::whereIn('attendance_session_id', $sessionIds)->count();
        $present = AttendanceEntry::whereIn('attendance_session_id', $sessionIds)->where('status', AttendanceStatus::PRESENT->value)->count();
        $absent  = AttendanceEntry::whereIn('attendance_session_id', $sessionIds)->where('status', AttendanceStatus::ABSENT->value)->count();
        $rate    = $total > 0 ? round(($present / $total) * 100, 1) : 0;
        $stats   = compact('total', 'present', 'absent', 'rate');

        $excelPath = $this->generateExcel(
            new AttendanceSummaryExport($task->school_id, $classId),
            'presences-semaine'
        );

        $admins = User::where('school_id', $task->school_id)->whereNotNull('email')->get();
        foreach ($admins as $admin) {
            Mail::send('emails.tasks.attendance-summary', compact('school', 'admin', 'stats'), function ($msg) use ($admin, $task, $excelPath) {
                $msg->to($admin->email)
                    ->subject($task->meta['custom_subject'] ?? "Résumé des présences — " . now()->format('d/m/Y'));
                if ($excelPath) {
                    $msg->attach($excelPath, [
                        'as'   => 'presences-' . now()->format('Ymd') . '.xlsx',
                        'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ]);
                }
            });
        }

        $this->sendToExtraRecipients($task, ['stats' => $stats], $excelPath, 'presences-semaine');
        $this->cleanupExcel($excelPath);
    }

    // ── Financial Summary ─────────────────────────────────────────────────────

    private function runFinancialSummary(ScheduledTask $task): void
    {
        $school  = $task->school;
        $revenue = Payment::where('school_id', $task->school_id)
            ->where('status', 'confirmed')
            ->whereBetween('payment_date', [now()->startOfMonth()->toDateString(), now()->toDateString()])
            ->sum('amount');
        $pending = Invoice::where('school_id', $task->school_id)
            ->whereNotIn('status', [InvoiceStatus::PAID->value, InvoiceStatus::CANCELLED->value])
            ->sum('balance_due');
        $overdue = Invoice::where('school_id', $task->school_id)->overdue()->sum('balance_due');
        $stats   = compact('revenue', 'pending', 'overdue');

        $excelPath = $this->generateExcel(
            new FinancialSummaryExport($task->school_id),
            'rapport-financier'
        );

        $admins = User::where('school_id', $task->school_id)->whereNotNull('email')->get();
        foreach ($admins as $admin) {
            Mail::send('emails.tasks.financial-summary', compact('school', 'admin', 'stats'), function ($msg) use ($admin, $task, $excelPath) {
                $msg->to($admin->email)
                    ->subject($task->meta['custom_subject'] ?? "Résumé financier — " . now()->format('F Y'));
                if ($excelPath) {
                    $msg->attach($excelPath, [
                        'as'   => 'rapport-financier-' . now()->format('Ymd') . '.xlsx',
                        'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ]);
                }
            });
        }

        $this->sendToExtraRecipients($task, ['stats' => $stats], $excelPath, 'rapport-financier');
        $this->cleanupExcel($excelPath);
    }

    // ── Custom Notification ───────────────────────────────────────────────────

    private function runCustomNotification(ScheduledTask $task): void
    {
        $school  = $task->school;
        $subject = $task->meta['custom_subject'] ?? $task->name;
        $body    = $task->meta['custom_body']    ?? '';

        $recipients = $this->resolveRecipients($task);

        foreach ($recipients as $recipient) {
            Mail::send('emails.tasks.custom-notification', compact('school', 'recipient', 'body', 'task'), function ($msg) use ($recipient, $subject) {
                $msg->to($recipient->email)->subject($subject);
            });
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function resolveRecipients(ScheduledTask $task): Collection
    {
        return match ($task->target_type) {
            'school_admins' => User::where('school_id', $task->school_id)->whereNotNull('email')->get(),

            'all_guardians' => Student::where('school_id', $task->school_id)
                ->with('guardians')
                ->get()
                ->flatMap(fn($s) => $s->guardians->whereNotNull('email'))
                ->unique('id')
                ->values(),

            'class_guardians' => Student::where('school_id', $task->school_id)
                ->whereHas('enrollments', fn($q) => $q->where('school_class_id', $task->target_id))
                ->with('guardians')
                ->get()
                ->flatMap(fn($s) => $s->guardians->whereNotNull('email'))
                ->unique('id')
                ->values(),

            'grade_guardians' => Student::where('school_id', $task->school_id)
                ->whereHas('enrollments', fn($q) => $q->where('grade_id', $task->target_id))
                ->with('guardians')
                ->get()
                ->flatMap(fn($s) => $s->guardians->whereNotNull('email'))
                ->unique('id')
                ->values(),

            default => collect(),
        };
    }

    /**
     * Send the task email to every extra recipient stored in meta:
     * - meta['recipient_user_ids']     → school Users
     * - meta['recipient_guardian_ids'] → Guardians
     *
     * Attaches the pre-generated Excel file if provided.
     */
    private function sendToExtraRecipients(ScheduledTask $task, array $context = [], ?string $excelPath = null, string $excelName = 'rapport'): void
    {
        $userIds     = $task->meta['recipient_user_ids']     ?? [];
        $guardianIds = $task->meta['recipient_guardian_ids'] ?? [];

        if (empty($userIds) && empty($guardianIds)) {
            return;
        }

        $school  = $task->school;
        $subject = $task->meta['custom_subject'] ?? $task->name;

        // Resolve the right template + data
        [$template, $data] = match ($task->type) {
            ScheduledTaskType::ATTENDANCE_SUMMARY  => [
                'emails.tasks.attendance-summary',
                array_merge(['school' => $school, 'stats' => $context['stats'] ?? []], $context),
            ],
            ScheduledTaskType::FINANCIAL_SUMMARY   => [
                'emails.tasks.financial-summary',
                array_merge(['school' => $school, 'stats' => $context['stats'] ?? []], $context),
            ],
            default => [
                'emails.tasks.custom-notification',
                ['school' => $school, 'task' => $task, 'body' => $task->meta['custom_body'] ?? $task->description ?? ''],
            ],
        };

        $attachFn = function ($msg) use ($excelPath, $excelName) {
            if ($excelPath) {
                $msg->attach($excelPath, [
                    'as'   => $excelName . '-' . now()->format('Ymd') . '.xlsx',
                    'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ]);
            }
        };

        // Send to extra users
        if (!empty($userIds)) {
            User::whereIn('id', $userIds)->whereNotNull('email')->get()
                ->each(function (User $user) use ($template, $data, $subject, $attachFn) {
                    $viewData = array_merge($data, ['admin' => $user, 'recipient' => $user]);
                    Mail::send($template, $viewData, function ($msg) use ($user, $subject, $attachFn) {
                        $msg->to($user->email)->subject($subject);
                        $attachFn($msg);
                    });
                });
        }

        // Send to extra guardians
        if (!empty($guardianIds)) {
            Guardian::whereIn('id', $guardianIds)->whereNotNull('email')->get()
                ->each(function (Guardian $guardian) use ($template, $data, $subject, $attachFn) {
                    $viewData = array_merge($data, ['admin' => $guardian, 'recipient' => $guardian]);
                    Mail::send($template, $viewData, function ($msg) use ($guardian, $subject, $attachFn) {
                        $msg->to($guardian->email)->subject($subject);
                        $attachFn($msg);
                    });
                });
        }
    }

    /**
     * Generate an Excel file to a temp path and return the path.
     * Returns null on failure (so email still sends without attachment).
     */
    private function generateExcel(mixed $export, string $name): ?string
    {
        try {
            $content = Excel::raw($export, \Maatwebsite\Excel\Excel::XLSX);
            $path    = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $name . '-' . now()->format('Ymd-His') . '-' . uniqid() . '.xlsx';
            file_put_contents($path, $content);
            return $path;
        } catch (\Throwable $e) {
            $this->warn("   Excel generation failed: " . $e->getMessage());
            return null;
        }
    }

    private function cleanupExcel(?string $path): void
    {
        if ($path && file_exists($path)) {
            @unlink($path);
        }
    }
}
