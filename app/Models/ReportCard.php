<?php

namespace App\Models;

use App\Enums\ReportPeriod;
use App\Traits\BelongsToSchool;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReportCard extends Model
{
    use BelongsToSchool, HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid',
        'school_id',
        'student_id',
        'enrollment_id',
        'academic_year_id',
        'period',
        'average',
        'class_average',
        'rank',
        'class_size',
        'general_comment',
        'teacher_comment',
        'is_published',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'period'       => ReportPeriod::class,
            'average'      => 'decimal:2',
            'class_average'=> 'decimal:2',
            'rank'         => 'integer',
            'class_size'   => 'integer',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function reportCardSubjects(): HasMany
    {
        return $this->hasMany(ReportCardSubject::class);
    }

    /** Blade-friendly alias */
    public function subjectGrades(): HasMany
    {
        return $this->hasMany(ReportCardSubject::class);
    }
}
