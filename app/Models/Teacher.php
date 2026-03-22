<?php

namespace App\Models;

use App\Enums\GenderType;
use App\Traits\Auditable;
use App\Traits\BelongsToSchool;
use App\Traits\HasAttachments;
use App\Traits\HasReference;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Teacher extends Model
{
    use Auditable, BelongsToSchool, HasAttachments, HasFactory, HasReference, HasUuid, SoftDeletes;

    public const REFERENCE_PREFIX = 'TCH';

    protected $fillable = [
        'uuid',
        'reference',
        'school_id',
        'user_id',
        'name',
        'gender',
        'date_of_birth',
        'national_id',
        'phone',
        'email',
        'specialization',
        'hire_date',
        'photo',
        'is_active',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'gender'        => GenderType::class,
            'date_of_birth' => 'date',
            'hire_date'     => 'date',
            'is_active'     => 'boolean',
            'meta'          => 'array',
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

    protected function photoUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->photo
                ? Storage::url($this->photo)
                : null,
        );
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'teacher_subject')
            ->withTimestamps();
    }

    public function schoolClasses(): BelongsToMany
    {
        return $this->belongsToMany(SchoolClass::class, 'teacher_school_class')
            ->withPivot('subject_id')
            ->withTimestamps();
    }
}
