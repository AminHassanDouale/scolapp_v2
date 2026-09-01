<?php

namespace App\Mail;

use App\Models\School;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Generic "your account is ready" email carrying login credentials.
 * Used for any staff/role user (surveillant, caissier, comptable, directeur…)
 * that does not have a role-specific welcome mailable.
 */
class AccountCredentialsMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User    $user,
        public readonly ?School $school,
        public readonly string  $plainPassword,
        public readonly string  $roleLabel = '',
        public readonly string  $loginUrl  = '',
    ) {}

    public function envelope(): Envelope
    {
        $name = $this->school?->name ?? config('app.name');

        return new Envelope(
            subject: "Bienvenue chez {$name} — Vos identifiants de connexion",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.account.credentials',
            with: [
                'user'          => $this->user,
                'school'        => $this->school,
                'plainPassword' => $this->plainPassword,
                'roleLabel'     => $this->roleLabel,
                'loginUrl'      => $this->loginUrl ?: url('/login'),
            ],
        );
    }
}
