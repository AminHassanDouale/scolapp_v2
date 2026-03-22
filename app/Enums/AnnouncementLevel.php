<?php

namespace App\Enums;

enum AnnouncementLevel: string
{
    case INFO    = 'info';
    case WARNING = 'warning';
    case URGENT  = 'urgent';

    public function label(): string
    {
        return match($this) {
            self::INFO    => __('enums.announcement_level.info'),
            self::WARNING => __('enums.announcement_level.warning'),
            self::URGENT  => __('enums.announcement_level.urgent'),
        };
    }

    public function color(): string
    {
        return match($this) {
            self::INFO    => 'info',
            self::WARNING => 'warning',
            self::URGENT  => 'error',
        };
    }
}
