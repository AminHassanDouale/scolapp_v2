<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case DRAFT          = 'draft';
    case ISSUED         = 'issued';
    case PARTIALLY_PAID = 'partially_paid';
    case PAID           = 'paid';
    case CANCELLED      = 'cancelled';
    case OVERDUE        = 'overdue';

    public function label(): string
    {
        return match($this) {
            self::DRAFT          => __('enums.invoice_status.draft'),
            self::ISSUED         => __('enums.invoice_status.issued'),
            self::PARTIALLY_PAID => __('enums.invoice_status.partially_paid'),
            self::PAID           => __('enums.invoice_status.paid'),
            self::CANCELLED      => __('enums.invoice_status.cancelled'),
            self::OVERDUE        => __('enums.invoice_status.overdue'),
        };
    }

    public function color(): string
    {
        return match($this) {
            self::DRAFT          => 'ghost',
            self::ISSUED         => 'info',
            self::PARTIALLY_PAID => 'warning',
            self::PAID           => 'success',
            self::CANCELLED      => 'error',
            self::OVERDUE        => 'error',
        };
    }

    public function isPaid(): bool
    {
        return $this === self::PAID;
    }

    public function isCancellable(): bool
    {
        return in_array($this, [self::DRAFT, self::ISSUED]);
    }
}
