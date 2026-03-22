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

class StudentRegisteredMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Student  $student,
        public readonly Guardian $guardian,
        public readonly School   $school,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Inscription de {$this->student->full_name} — {$this->school->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.student.registered',
            with: [
                'student'  => $this->student,
                'guardian' => $this->guardian,
                'school'   => $this->school,
            ],
        );
    }
}
