<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentScore extends Model
{
    use HasFactory;

    protected $fillable = [
        'assessment_id',
        'student_id',
        'score',
        'is_absent',
        'mention',
        'comment',
    ];

    protected function casts(): array
    {
        return [
            'score'     => 'decimal:2',
            'is_absent' => 'boolean',
        ];
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    protected function percentage(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->is_absent || $this->score === null) {
                    return null;
                }

                $maxScore = $this->assessment?->max_score;

                if (! $maxScore || $maxScore == 0) {
                    return null;
                }

                return round(($this->score / $maxScore) * 100, 2);
            },
        );
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
