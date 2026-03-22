<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportCardSubject extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_card_id',
        'subject_id',
        'average',
        'coefficient',
        'weighted_average',
        'rank',
        'absences',
        'teacher_comment',
        'mention',
    ];

    protected function casts(): array
    {
        return [
            'average'          => 'decimal:2',
            'coefficient'      => 'decimal:2',
            'weighted_average' => 'decimal:2',
            'rank'             => 'integer',
            'absences'         => 'integer',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function reportCard(): BelongsTo
    {
        return $this->belongsTo(ReportCard::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}
