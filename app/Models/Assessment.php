<?php

namespace App\Models;

use App\Enums\AssessmentType;
use App\Enums\ReportPeriod;
use App\Traits\BelongsToSchool;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Assessment extends Model
{
    use BelongsToSchool, HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid',
        'school_id',
        'school_class_id',
        'subject_id',
        'teacher_id',
        'academic_year_id',
        'title',
        'type',
        'period',
        'max_score',
        'coefficient',
        'assessment_date',
        'is_published',
        'instructions',
        'file_path',
        'file_original_name',
    ];

    protected function casts(): array
    {
        return [
            'type'            => AssessmentType::class,
            'period'          => ReportPeriod::class,
            'max_score'       => 'decimal:2',
            'coefficient'     => 'decimal:2',
            'assessment_date' => 'date',
            'is_published'    => 'boolean',
        ];
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    protected function fileUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->file_path
                ? Storage::disk('public')->url($this->file_path)
                : null,
        );
    }

    protected function fileExtension(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->file_path
                ? strtolower(pathinfo($this->file_path, PATHINFO_EXTENSION))
                : null,
        );
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

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function studentScores(): HasMany
    {
        return $this->hasMany(StudentScore::class);
    }
}
