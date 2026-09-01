<?php

namespace App\Services;

use App\Mail\AccountCredentialsMail;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends freshly-created login credentials to a user over BOTH channels:
 * email (AccountCredentialsMail) and WhatsApp (via WhatsAppService).
 *
 * Failures on one channel never block the other, and never bubble up to the
 * caller — a broken mail/WhatsApp gateway must not fail user creation.
 */
class CredentialsNotifier
{
    public function __construct(private WhatsAppService $whatsapp) {}

    /**
     * @param  User        $user          the created account
     * @param  string      $plainPassword the clear password to deliver
     * @param  string      $roleLabel     e.g. "Enseignant", "Surveillant", "Parent"
     * @param  string|null $loginUrl      portal login URL (defaults to /login)
     * @param  Model|null  $whatsappTo    model to resolve the WhatsApp number from
     *                                    (falls back to the user itself)
     */
    public function send(
        User $user,
        string $plainPassword,
        string $roleLabel = '',
        ?string $loginUrl = null,
        ?Model $whatsappTo = null,
    ): void {
        if ($plainPassword === '') {
            return;
        }

        $school   = $user->school_id ? School::find($user->school_id) : null;
        $loginUrl ??= url('/login');

        // ── Email ─────────────────────────────────────────────────────────
        if ($user->email) {
            try {
                Mail::to($user->email)->send(
                    new AccountCredentialsMail($user, $school, $plainPassword, $roleLabel, $loginUrl)
                );
            } catch (\Throwable $e) {
                Log::warning('Credentials email failed for user ' . $user->id . ': ' . $e->getMessage());
            }
        }

        // ── WhatsApp ──────────────────────────────────────────────────────
        $this->sendWhatsappOnly($user, $plainPassword, $roleLabel, $loginUrl, $whatsappTo);
    }

    /**
     * Deliver credentials over WhatsApp only. Use this when a role-specific
     * welcome email has already been sent and you only need the WhatsApp copy.
     */
    public function sendWhatsappOnly(
        User $user,
        string $plainPassword,
        string $roleLabel = '',
        ?string $loginUrl = null,
        ?Model $whatsappTo = null,
    ): void {
        if ($plainPassword === '') {
            return;
        }

        $school   = $user->school_id ? School::find($user->school_id) : null;
        $loginUrl ??= url('/login');

        try {
            $text = "🔐 *" . ($school->name ?? config('app.name')) . "* — Vos identifiants\n"
                . ($roleLabel ? "Rôle : {$roleLabel}\n" : '')
                . "Connexion : {$loginUrl}\n"
                . "Identifiant : {$user->email}\n"
                . "Mot de passe : {$plainPassword}\n"
                . "Merci de changer votre mot de passe après la première connexion.";

            $this->whatsapp->notifyModel($whatsappTo ?? $user, $text);
        } catch (\Throwable $e) {
            Log::warning('Credentials WhatsApp failed for user ' . $user->id . ': ' . $e->getMessage());
        }
    }
}
