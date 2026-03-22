<?php

namespace App\Mail;

use App\Models\School;
use App\Models\Teacher;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TeacherWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Teacher $teacher,
        public readonly School  $school,
        public readonly string  $plainPassword = '',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Bienvenue chez {$this->school->name} — Vos identifiants de connexion",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.teacher.welcome',
            with: [
                'teacher'       => $this->teacher,
                'school'        => $this->school,
                'plainPassword' => $this->plainPassword,
                'loginUrl'      => url('/teacher'),
            ],
        );
    }
}
