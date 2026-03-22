<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use App\Traits\Auditable;
use App\Traits\BelongsToSchool;
use App\Traits\HasReference;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Enrollment extends Model
{
    use Auditable, BelongsToSchool, HasFactory, HasReference, HasUuid, SoftDeletes;

    public const REFERENCE_PREFIX = 'ENR';

    protected $fillable = [
        'uuid',
        'reference',
        'school_id',
        'student_id',
        'academic_year_id',
        'school_class_id',
        'grade_id',
        'status',
        'enrolled_at',
        'confirmed_at',
        'cancelled_at',
        'cancelled_reason',
        'notes',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'status'       => EnrollmentStatus::class,
            'enrolled_at'  => 'date',
            'confirmed_at' => 'date',
            'cancelled_at' => 'date',
            'meta'         => 'array',
        ];
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', EnrollmentStatus::CONFIRMED);
    }

    public function scopeHold(Builder $query): Builder
    {
        return $query->where('status', EnrollmentStatus::HOLD);
    }

    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', EnrollmentStatus::CANCELLED);
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function studentFeePlan(): HasOne
    {
        return $this->hasOne(StudentFeePlan::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function reportCards(): HasMany
    {
        return $this->hasMany(ReportCard::class);
    }
}
