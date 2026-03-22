<?php

namespace App\Mail;

use App\Models\Guardian;
use App\Models\ReportCard;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReportCardPublishedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly ReportCard $reportCard,
        public readonly Guardian   $guardian,
    ) {}

    public function envelope(): Envelope
    {
        $student = $this->reportCard->enrollment?->student;
        $period  = $this->reportCard->period?->label() ?? $this->reportCard->period;

        return new Envelope(
            subject: "Bulletin disponible — {$student?->full_name} — {$period}",
        );
    }

    public function content(): Content
    {
        $rc = $this->reportCard->load([
            'enrollment.student',
            'enrollment.schoolClass.grade',
            'enrollment.academicYear',
            'subjectGrades.subject',
        ]);

        return new Content(
            markdown: 'emails.report-card.published',
            with: [
                'reportCard' => $rc,
                'guardian'   => $this->guardian,
                'school'     => $rc->enrollment?->student?->school,
            ],
        );
    }
}
