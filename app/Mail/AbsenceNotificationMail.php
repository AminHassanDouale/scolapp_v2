<?php

namespace App\Mail;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceSession;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AbsenceNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Guardian         $guardian,
        public readonly Student          $student,
        public readonly AttendanceSession $session,
        public readonly string           $status,   // 'absent' | 'late' | 'excused'
        public readonly ?string          $reason,
    ) {}

    public function envelope(): Envelope
    {
        $class = $this->session->schoolClass?->name ?? '';
        $date  = $this->session->session_date?->format('d/m/Y') ?? now()->format('d/m/Y');

        $label = match ($this->status) {
            'absent'  => 'Absence',
            'late'    => 'Retard',
            'excused' => 'Absence excusée',
            default   => 'Absence',
        };

        return new Envelope(
            subject: "{$label} de {$this->student->full_name} — {$class} — {$date}",
        );
    }

    public function content(): Content
    {
        $session = $this->session;
        $session->loadMissing(['schoolClass.grade', 'subject', 'teacher', 'academicYear']);

        $school = School::find($session->school_id);

        $statusLabel = match ($this->status) {
            'absent'  => 'Absent(e)',
            'late'    => 'En retard',
            'excused' => 'Absent(e) — excusé(e)',
            default   => $this->status,
        };

        $periodLabel = match ($session->period ?? '') {
            'morning'   => 'Matin',
            'afternoon' => 'Après-midi',
            'full_day'  => 'Journée entière',
            default     => $session->period ?? '—',
        };

        return new Content(
            markdown: 'emails.attendance.absence-notification',
            with: [
                'guardian'    => $this->guardian,
                'student'     => $this->student,
                'session'     => $session,
                'statusLabel' => $statusLabel,
                'periodLabel' => $periodLabel,
                'reason'      => $this->reason,
                'school'      => $school,
            ],
        );
    }
}
