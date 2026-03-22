<?php

namespace App\Models;

use App\Enums\GenderType;
use App\Traits\BelongsToSchool;
use App\Traits\HasAttachments;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Guardian extends Model
{
    use BelongsToSchool, HasAttachments, HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid',
        'school_id',
        'user_id',
        'name',
        'gender',
        'phone',
        'phone_secondary',
        'email',
        'profession',
        'national_id',
        'address',
        'photo',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'gender'    => GenderType::class,
            'is_active' => 'boolean',
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

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'student_guardian')
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
