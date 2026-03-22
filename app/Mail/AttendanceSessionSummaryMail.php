<?php

namespace App\Mail;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceSession;
use App\Models\School;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AttendanceSessionSummaryMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly AttendanceSession $session,
    ) {}

    public function envelope(): Envelope
    {
        $class = $this->session->schoolClass?->name ?? 'Classe';
        $date  = $this->session->session_date?->format('d/m/Y') ?? now()->format('d/m/Y');

        return new Envelope(
            subject: "Récapitulatif d'appel — {$class} — {$date}",
        );
    }

    public function content(): Content
    {
        $session = $this->session;
        $session->loadMissing([
            'attendanceEntries.student',
            'schoolClass.grade',
            'subject',
            'teacher',
            'academicYear',
        ]);

        $entries = $session->attendanceEntries->sortBy(fn($e) => $e->student?->last_name);
        $total   = $entries->count();
        $present = $entries->filter(fn($e) => $e->status === AttendanceStatus::PRESENT)->count();
        $absent  = $entries->filter(fn($e) => $e->status === AttendanceStatus::ABSENT)->count();
        $late    = $entries->filter(fn($e) => $e->status === AttendanceStatus::LATE)->count();
        $excused = $entries->filter(fn($e) => $e->status === AttendanceStatus::EXCUSED)->count();
        $rate    = $total > 0 ? round(($present / $total) * 100) : 0;

        $school = School::find($session->school_id);

        $periodLabel = match ($session->period ?? '') {
            'morning'   => 'Matin',
            'afternoon' => 'Après-midi',
            'full_day'  => 'Journée entière',
            default     => $session->period ?? '—',
        };

        return new Content(
            markdown: 'emails.attendance.session-summary',
            with: compact(
                'session', 'entries',
                'total', 'present', 'absent', 'late', 'excused', 'rate',
                'school', 'periodLabel'
            ),
        );
    }
}
