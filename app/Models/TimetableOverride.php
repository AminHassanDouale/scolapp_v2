<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimetableOverride extends Model
{
    protected $fillable = [
        'timetable_entry_id',
        'substitute_teacher_id',
        'override_date',
        'override_room',
        'is_cancelled',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'override_date' => 'date',
            'is_cancelled'  => 'boolean',
        ];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(TimetableEntry::class, 'timetable_entry_id');
    }

    public function substituteTeacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'substitute_teacher_id');
    }
}
