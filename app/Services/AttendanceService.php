<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceEntry;
use App\Models\AttendanceSession;
use App\Models\SchoolClass;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    public function openSession(
        SchoolClass $class,
        string      $period,
        Carbon      $date = null,
        array       $options = []
    ): AttendanceSession {
        $date ??= now();

        return AttendanceSession::firstOrCreate(
            [
                'school_class_id'  => $class->id,
                'academic_year_id' => $options['academic_year_id'] ?? $class->academic_year_id,
                'session_date'     => $date->toDateString(),
                'period'           => $period,
                'subject_id'       => $options['subject_id'] ?? null,
            ],
            [
                'school_id'  => $class->school_id,
                'teacher_id' => $options['teacher_id'] ?? null,
                'start_time' => $options['start_time'] ?? null,
                'end_time'   => $options['end_time']   ?? null,
            ]
        );
    }

    public function markAttendance(
        AttendanceSession $session,
        array             $entries  // [student_id => ['status' => '...', 'reason' => '...']]
    ): void {
        DB::transaction(function () use ($session, $entries) {
            foreach ($entries as $studentId => $data) {
                AttendanceEntry::updateOrCreate(
                    [
                        'attendance_session_id' => $session->id,
                        'student_id'            => $studentId,
                    ],
                    [
                        'status' => $data['status'],
                        'reason' => $data['reason'] ?? null,
                    ]
                );
            }
        });
    }

    public function getStudentAbsences(
        int     $studentId,
        int     $schoolId,
        ?string $from = null,
        ?string $to   = null
    ): Collection {
        return AttendanceEntry::query()
            ->whereHas('attendanceSession', function ($q) use ($schoolId, $from, $to) {
                $q->where('school_id', $schoolId);
                if ($from) {
                    $q->whereDate('session_date', '>=', $from);
                }
                if ($to) {
                    $q->whereDate('session_date', '<=', $to);
                }
            })
            ->where('student_id', $studentId)
            ->whereIn('status', [
                AttendanceStatus::ABSENT->value,
                AttendanceStatus::LATE->value,
            ])
            ->with('attendanceSession')
            ->get();
    }

    public function getClassAttendanceSummary(SchoolClass $class, Carbon $month): array
    {
        $sessions = AttendanceSession::where('school_class_id', $class->id)
            ->whereYear('session_date', $month->year)
            ->whereMonth('session_date', $month->month)
            ->with('attendanceEntries')
            ->get();

        $summary = [];

        foreach ($sessions as $session) {
            foreach ($session->attendanceEntries as $entry) {
                $sid = $entry->student_id;
                $summary[$sid] ??= [
                    'present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0,
                ];
                $summary[$sid][$entry->status->value]++;
            }
        }

        return $summary;
    }
}
