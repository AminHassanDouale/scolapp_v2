<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case PRESENT = 'present';
    case ABSENT  = 'absent';
    case LATE    = 'late';
    case EXCUSED = 'excused';

    public function label(): string
    {
        return match($this) {
            self::PRESENT => __('enums.attendance_status.present'),
            self::ABSENT  => __('enums.attendance_status.absent'),
            self::LATE    => __('enums.attendance_status.late'),
            self::EXCUSED => __('enums.attendance_status.excused'),
        };
    }

    public function color(): string
    {
        return match($this) {
            self::PRESENT => 'success',
            self::ABSENT  => 'error',
            self::LATE    => 'warning',
            self::EXCUSED => 'info',
        };
    }

    public function isAbsence(): bool
    {
        return in_array($this, [self::ABSENT, self::LATE, self::EXCUSED]);
    }
}
