<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceToken extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid',
        'user_id',
        'school_id',
        'token',
        'platform',
        'device_name',
        'device_model',
        'os_version',
        'app_version',
        'last_used_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'is_active'    => 'boolean',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForPlatform(Builder $query, string $platform): Builder
    {
        return $query->where('platform', $platform);
    }

    public function scopeForSchool(Builder $query, int $schoolId): Builder
    {
        return $query->where('school_id', $schoolId);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Register or update a device token for a user.
     * Prevents duplicates — one record per token value.
     */
    public static function upsertForUser(User $user, array $data): self
    {
        return static::updateOrCreate(
            ['token' => $data['token']],
            array_merge($data, [
                'user_id'      => $user->id,
                'school_id'    => $user->school_id,
                'last_used_at' => now(),
                'is_active'    => true,
            ])
        );
    }

    /**
     * Mark the token as used (call on successful push delivery).
     */
    public function touch($field = null): bool
    {
        $this->update(['last_used_at' => now()]);
        return parent::touch($field);
    }

    /**
     * Deactivate stale tokens (e.g. after FCM reports "not registered").
     */
    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }
}
