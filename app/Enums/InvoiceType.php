<?php

namespace App\Enums;

enum InvoiceType: string
{
    case REGISTRATION = 'registration';
    case TUITION      = 'tuition';

    public function label(): string
    {
        return match($this) {
            self::REGISTRATION => __('enums.invoice_type.registration'),
            self::TUITION      => __('enums.invoice_type.tuition'),
        };
    }
}
