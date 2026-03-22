<?php

namespace App\Traits;

trait HasReference
{
    protected static function bootHasReference(): void
    {
        static::creating(function ($model) {
            if (empty($model->reference)) {
                $model->reference = static::generateReference();
            }
        });
    }

    public static function generateReference(): string
    {
        $prefix = defined('static::REFERENCE_PREFIX') ? static::REFERENCE_PREFIX : 'REF';
        $year   = now()->format('Y');
        $seq    = str_pad(static::withTrashed()->whereYear('created_at', $year)->count() + 1, 5, '0', STR_PAD_LEFT);
        return "{$prefix}{$year}{$seq}";
    }
}
