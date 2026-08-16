<?php

namespace App\Models;

use App\Enums\AnnouncementLevel;
use App\Traits\BelongsToSchool;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Announcement extends Model
{
    use BelongsToSchool, HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid',
        'school_id',
        'created_by',
        'title',
        'body',
        'level',
        'is_pinned',
        'is_published',
        'published_at',
        'expires_at',
        'target_audience',
    ];

    protected function casts(): array
    {
        return [
            'level'           => AnnouncementLevel::class,
            'is_pinned'       => 'boolean',
            'is_published'    => 'boolean',
            'published_at'    => 'datetime',
            'expires_at'      => 'datetime',
            'target_audience' => 'array',
        ];
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopePinned(Builder $query): Builder
    {
        return $query->where('is_pinned', true);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('is_published', true)
            ->where(function (Builder $q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            });
    }

    /** Published, past its (optional) scheduled date, and not expired. */
    public function scopePublishedNow(Builder $query): Builder
    {
        return $query
            ->where('is_published', true)
            ->where(fn (Builder $q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    /**
     * Restrict to announcements targeting a given audience.
     * $audience: 'teachers' | 'guardians' | 'students'. $classIds: the viewer's classes.
     */
    public function scopeForAudience(Builder $query, string $audience, array $classIds = []): Builder
    {
        return $query->where(function (Builder $q) use ($audience, $classIds) {
            $q->whereNull('target_audience')
              ->orWhere('target_audience->type', 'all')
              ->orWhere('target_audience->type', $audience);

            if (! empty($classIds)) {
                $q->orWhere(function (Builder $c) use ($classIds) {
                    $c->where('target_audience->type', 'class')
                      ->where(function (Builder $cc) use ($classIds) {
                          foreach ($classIds as $cid) {
                              $cc->orWhereJsonContains('target_audience->class_ids', (int) $cid);
                          }
                      });
                });
            }
        });
    }

    /** Human label for the current target audience. */
    public function audienceLabel(): string
    {
        $ta = $this->target_audience;
        $type = is_array($ta) ? ($ta['type'] ?? 'all') : ($ta ?: 'all');

        return match ($type) {
            'teachers'  => 'Enseignants',
            'guardians' => 'Parents',
            'students'  => 'Élèves',
            'class'     => 'Classes ciblées',
            default     => 'Tous',
        };
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
