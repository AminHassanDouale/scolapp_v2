<?php

namespace App\Enums;

enum EnrollmentStatus: string
{
    case HOLD      = 'hold';
    case CONFIRMED = 'confirmed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::HOLD      => __('enums.enrollment_status.hold'),
            self::CONFIRMED => __('enums.enrollment_status.confirmed'),
            self::CANCELLED => __('enums.enrollment_status.cancelled'),
        };
    }

    public function color(): string
    {
        return match($this) {
            self::HOLD      => 'warning',
            self::CONFIRMED => 'success',
            self::CANCELLED => 'error',
        };
    }
}
