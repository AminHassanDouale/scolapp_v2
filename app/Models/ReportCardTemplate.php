<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportCardTemplate extends Model
{
    protected $fillable = [
        'school_id',
        'header_title',
        'subtitle',
        'instructions',
        'footer_text',
        'principal_name',
        'principal_title',
        'mention_tb_min',
        'mention_b_min',
        'mention_ab_min',
        'mention_p_min',
        'show_rank',
        'show_class_avg',
        'show_absences',
        'show_teacher_comment',
    ];

    protected function casts(): array
    {
        return [
            'mention_tb_min'       => 'decimal:1',
            'mention_b_min'        => 'decimal:1',
            'mention_ab_min'       => 'decimal:1',
            'mention_p_min'        => 'decimal:1',
            'show_rank'            => 'boolean',
            'show_class_avg'       => 'boolean',
            'show_absences'        => 'boolean',
            'show_teacher_comment' => 'boolean',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Get or create the template for a given school.
     */
    public static function forSchool(int $schoolId): self
    {
        return self::firstOrCreate(
            ['school_id' => $schoolId],
            ['header_title' => 'Bulletin Scolaire', 'principal_title' => 'Le Directeur']
        );
    }

    /**
     * Compute mention label and badge class for a given average.
     */
    public function mention(float|null $avg): array
    {
        if ($avg === null) {
            return ['label' => '—', 'badge' => 'badge-ghost'];
        }
        if ($avg >= $this->mention_tb_min) {
            return ['label' => 'Très Bien', 'badge' => 'badge-success'];
        }
        if ($avg >= $this->mention_b_min) {
            return ['label' => 'Bien', 'badge' => 'badge-info'];
        }
        if ($avg >= $this->mention_ab_min) {
            return ['label' => 'Assez Bien', 'badge' => 'badge-primary'];
        }
        if ($avg >= $this->mention_p_min) {
            return ['label' => 'Passable', 'badge' => 'badge-warning'];
        }
        return ['label' => 'Insuffisant', 'badge' => 'badge-error'];
    }
}
