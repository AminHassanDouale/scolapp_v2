<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\HasUuid;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasApiTokens, HasFactory, HasRoles, HasUuid, Notifiable, SoftDeletes;

    protected $fillable = [
        'uuid',
        'reference',
        'school_id',
        'name',
        'email',
        'password',
        'phone',
        'avatar',
        'ui_lang',
        'timezone',
        'whatsapp_number',
        'is_blocked',
        'blocked_reason',
        'blocked_at',
        'last_login_at',
        'last_login_ip',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_blocked'        => 'boolean',
            'blocked_at'        => 'datetime',
            'last_login_at'     => 'datetime',
        ];
    }

    // ── Accessors ──────────────────────────────────────────────────────────────

    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->name,
        );
    }

    protected function avatarUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->avatar
                ? Storage::url($this->avatar)
                : 'https://ui-avatars.com/api/?name=' . urlencode($this->full_name) . '&color=7F9CF5&background=EBF4FF',
        );
    }

    // ── Portal routing ─────────────────────────────────────────────────────────

    /**
     * Returns the correct dashboard route for this user based on their role.
     * Used by login redirect and redirectUsersTo middleware.
     */
    public function portalRoute(): string
    {
        return match (true) {
            $this->hasRole('super-admin') => route('platform.dashboard'),
            $this->hasRole('admin')       => route('admin.dashboard'),
            $this->hasRole('director')    => route('admin.dashboard'),
            $this->hasRole('accountant')  => route('admin.dashboard'),
            $this->hasRole('teacher')     => route('teacher.dashboard'),
            $this->hasRole('monitor')     => route('monitor.dashboard'),
            $this->hasRole('guardian')    => route('guardian.dashboard'),
            $this->hasRole('student')     => route('student.dashboard'),
            $this->hasRole('caissier')    => route('caissier.dashboard'),
            // No recognised role — send to login to avoid redirect loops
            default                       => route('login'),
        };
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_blocked', false);
    }

    public function scopeForSchool(Builder $query, ?int $schoolId = null): Builder
    {
        $schoolId ??= auth()->user()?->school_id;
        return $query->where('school_id', $schoolId);
    }

    // ── Methods ───────────────────────────────────────────────────────────────

    public function block(string $reason): void
    {
        $this->update([
            'is_blocked'     => true,
            'blocked_reason' => $reason,
            'blocked_at'     => now(),
        ]);
    }

    public function unblock(): void
    {
        $this->update([
            'is_blocked'     => false,
            'blocked_reason' => null,
            'blocked_at'     => null,
        ]);
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function deviceTokens(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    public function activeDeviceTokens(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DeviceToken::class)->where('is_active', true);
    }
}
