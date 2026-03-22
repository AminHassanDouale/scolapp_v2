<?php

namespace App\Traits;

use App\Models\School;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToSchool
{
    protected static function bootBelongsToSchool(): void
    {
        static::creating(function ($model) {
            if (empty($model->school_id) && auth()->check()) {
                $model->school_id = auth()->user()->school_id;
            }
        });
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function scopeForSchool(Builder $query, int $schoolId = null): Builder
    {
        $schoolId ??= auth()->user()?->school_id;
        return $query->where($this->getTable() . '.school_id', $schoolId);
    }
}
