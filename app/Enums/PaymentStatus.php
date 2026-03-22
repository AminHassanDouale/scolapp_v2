<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case PENDING   = 'pending';
    case CONFIRMED = 'confirmed';
    case CANCELLED = 'cancelled';
    case REFUNDED  = 'refunded';

    public function label(): string
    {
        return match($this) {
            self::PENDING   => __('enums.payment_status.pending'),
            self::CONFIRMED => __('enums.payment_status.confirmed'),
            self::CANCELLED => __('enums.payment_status.cancelled'),
            self::REFUNDED  => __('enums.payment_status.refunded'),
        };
    }

    public function color(): string
    {
        return match($this) {
            self::PENDING   => 'warning',
            self::CONFIRMED => 'success',
            self::CANCELLED => 'error',
            self::REFUNDED  => 'info',
        };
    }
}
