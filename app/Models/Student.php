<?php

namespace App\Models;

use App\Enums\GenderType;
use App\Traits\Auditable;
use App\Traits\BelongsToSchool;
use App\Traits\HasAttachments;
use App\Traits\HasReference;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Student extends Model
{
    use Auditable, BelongsToSchool, HasAttachments, HasFactory, HasReference, HasUuid, SoftDeletes;

    public const REFERENCE_PREFIX = 'STU';

    protected $fillable = [
        'uuid',
        'reference',
        'student_code',
        'school_id',
        'user_id',
        'name',
        'gender',
        'date_of_birth',
        'place_of_birth',
        'nationality',
        'national_id',
        'photo',
        'address',
        'blood_type',
        'has_disability',
        'disability_notes',
        'is_active',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'gender'          => GenderType::class,
            'date_of_birth'   => 'date',
            'has_disability'  => 'boolean',
            'is_active'       => 'boolean',
            'meta'            => 'array',
        ];
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->name,
        );
    }

    protected function age(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->date_of_birth?->age,
        );
    }

    protected function photoUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->photo
                ? Storage::url($this->photo)
                : asset('images/student-placeholder.png'),
        );
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function currentEnrollment(): HasOne
    {
        return $this->hasOne(Enrollment::class)
            ->where('status', \App\Enums\EnrollmentStatus::CONFIRMED)
            ->latestOfMany('enrolled_at');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function attendanceEntries(): HasMany
    {
        return $this->hasMany(AttendanceEntry::class);
    }

    public function studentScores(): HasMany
    {
        return $this->hasMany(StudentScore::class);
    }

    public function reportCards(): HasMany
    {
        return $this->hasMany(ReportCard::class);
    }

    public function carnetEntries(): HasMany
    {
        return $this->hasMany(CarnetEntry::class);
    }

    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class, 'student_guardian')
            ->withPivot([
                'relation',
                'is_primary',
                'has_custody',
                'can_pickup',
                'receive_notifications',
            ])
            ->withTimestamps();
    }
}
