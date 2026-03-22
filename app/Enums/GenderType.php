<?php

namespace App\Enums;

enum GenderType: string
{
    case MALE   = 'male';
    case FEMALE = 'female';

    public function label(): string
    {
        return match($this) {
            self::MALE   => __('enums.gender.male'),
            self::FEMALE => __('enums.gender.female'),
        };
    }
}
