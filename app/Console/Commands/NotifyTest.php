<?php

namespace App\Console\Commands;

use App\Services\WhatsAppService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Send a one-off test email + WhatsApp to verify both notification channels
 * are wired and configured. Defaults target the test recipients requested for
 * the WhatsApp/email rollout.
 *
 *   php artisan notify:test
 *   php artisan notify:test --email=you@example.com --whatsapp=25377049495
 */
class NotifyTest extends Command
{
    protected $signature = 'notify:test
                            {--email=aminodiin1995@gmail.com : Test email recipient}
                            {--whatsapp=25377049495 : Test WhatsApp recipient (country code + number, digits only)}';

    protected $description = 'Send a test email + WhatsApp message to verify both notification channels';

    public function handle(WhatsAppService $whatsapp): int
    {
        $email = (string) $this->option('email');
        $phone = (string) $this->option('whatsapp');
        $app   = config('app.name', 'ScolApp');
        $now   = now()->format('d/m/Y H:i');

        $emailOk = false;
        $waOk    = false;

        // ── Email ──────────────────────────────────────────────────────────────
        $this->info("→ Sending test email to {$email} …");
        try {
            Mail::raw(
                "Ceci est un email de test de {$app}.\n\n"
                . "Si vous recevez ce message, les notifications par email fonctionnent correctement.\n\n"
                . "Envoyé le {$now}.",
                function ($m) use ($email, $app) {
                    $m->to($email)->subject("[{$app}] Test de notification — Email ✅");
                }
            );
            $emailOk = true;
            $this->info('  ✅ Email dispatched (mailer: ' . config('mail.default') . ').');
        } catch (\Throwable $e) {
            $this->error('  ❌ Email failed: ' . $e->getMessage());
        }

        // ── WhatsApp ───────────────────────────────────────────────────────────
        $this->info("→ Sending test WhatsApp to {$phone} …");
        $waOk = $whatsapp->sendMessage(
            $phone,
            "✅ *{$app}* — Test de notification WhatsApp.\n\n"
            . "Si vous recevez ce message, la passerelle WhatsApp (OpenWA) est opérationnelle.\n\n"
            . "Envoyé le {$now}."
        );
        $waOk
            ? $this->info('  ✅ WhatsApp sent.')
            : $this->error('  ❌ WhatsApp failed — check OPENWA_API_KEY / OPENWA_SESSION_ID and storage/logs/laravel.log.');

        $this->newLine();
        $this->line("Result: email " . ($emailOk ? 'OK' : 'FAILED') . " → {$email} | WhatsApp " . ($waOk ? 'OK' : 'FAILED') . " → {$phone}");

        return ($emailOk && $waOk) ? self::SUCCESS : self::FAILURE;
    }
}
