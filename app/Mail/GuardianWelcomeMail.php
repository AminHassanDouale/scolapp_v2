<?php

namespace App\Mail;

use App\Models\Guardian;
use App\Models\School;
use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GuardianWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Guardian $guardian,
        public readonly School   $school,
        public readonly Student  $student,
        public readonly string   $plainPassword,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Espace parent — {$this->school->name} : vos identifiants de connexion",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.guardian.welcome',
            with: [
                'guardian'      => $this->guardian,
                'school'        => $this->school,
                'student'       => $this->student,
                'plainPassword' => $this->plainPassword,
                'loginUrl'      => route('login'),
            ],
        );
    }
}
