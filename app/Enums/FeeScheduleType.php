<?php

namespace App\Enums;

enum FeeScheduleType: string
{
    case MONTHLY    = 'monthly';
    case BIMONTHLY  = 'bimonthly';
    case QUARTERLY  = 'quarterly';
    case YEARLY     = 'yearly';

    public function label(): string
    {
        return match($this) {
            self::MONTHLY   => 'Mensuel',
            self::BIMONTHLY => 'Bimestriel',
            self::QUARTERLY => 'Trimestriel',
            self::YEARLY    => 'Annuel',
        };
    }

    public function installments(): int
    {
        return match($this) {
            self::MONTHLY   => 10,  // Sept – Jun
            self::BIMONTHLY => 5,   // every 2 months
            self::QUARTERLY => 3,
            self::YEARLY    => 1,
        };
    }
}
