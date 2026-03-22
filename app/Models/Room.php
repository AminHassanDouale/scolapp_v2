<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Room extends Model
{
    use BelongsToSchool, HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid',
        'school_id',
        'name',
        'code',
        'type',
        'capacity',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'capacity'  => 'integer',
            'is_active' => 'boolean',
        ];
    }

    // Type labels
    public static array $types = [
        'classroom' => 'Salle de classe',
        'lab'       => 'Laboratoire',
        'gym'       => 'Gymnase',
        'outdoor'   => 'Terrain extérieur',
        'other'     => 'Autre',
    ];

    public function getTypeLabelAttribute(): string
    {
        return static::$types[$this->type] ?? $this->type;
    }

    public function entries(): HasMany
    {
        return $this->hasMany(TimetableEntry::class);
    }
}
