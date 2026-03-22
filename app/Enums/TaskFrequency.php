<?php

namespace App\Enums;

enum TaskFrequency: string
{
    case DAILY   = 'daily';
    case WEEKLY  = 'weekly';
    case MONTHLY = 'monthly';

    public function label(): string
    {
        return match($this) {
            self::DAILY   => 'Quotidien',
            self::WEEKLY  => 'Hebdomadaire',
            self::MONTHLY => 'Mensuel',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::DAILY   => 'o-arrow-path',
            self::WEEKLY  => 'o-calendar',
            self::MONTHLY => 'o-calendar-days',
        };
    }
}
