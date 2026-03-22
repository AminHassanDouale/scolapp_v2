<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TimetableEntry extends Model
{
    protected $fillable = [
        'timetable_template_id',
        'teacher_id',
        'subject_id',
        'room_id',
        'room',
        'day_of_week',
        'start_time',
        'end_time',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'room_id'     => 'integer',
        ];
    }

    /** Display room: prefer linked Room model name, fall back to legacy text. */
    public function getDisplayRoomAttribute(): ?string
    {
        return $this->roomModel?->name ?? $this->room;
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function template(): BelongsTo
    {
        return $this->belongsTo(TimetableTemplate::class, 'timetable_template_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function roomModel(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'room_id');
    }

    public function overrides(): HasMany
    {
        return $this->hasMany(TimetableOverride::class);
    }
}
