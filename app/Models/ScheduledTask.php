<?php

namespace App\Models;

use App\Enums\ScheduledTaskType;
use App\Enums\TaskFrequency;
use App\Traits\HasUuid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ScheduledTask extends Model
{
    use HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid', 'school_id', 'created_by', 'name', 'description',
        'type', 'target_type', 'target_id', 'frequency',
        'scheduled_time', 'day_of_week', 'day_of_month',
        'meta', 'is_active', 'last_run_at', 'next_run_at',
        'run_count', 'failure_count', 'last_error',
    ];

    protected function casts(): array
    {
        return [
            'type'        => ScheduledTaskType::class,
            'frequency'   => TaskFrequency::class,
            'meta'        => 'array',
            'is_active'   => 'boolean',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function targetClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'target_id');
    }

    public function targetGrade(): BelongsTo
    {
        return $this->belongsTo(Grade::class, 'target_id');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function computeNextRunAt(): Carbon
    {
        [$h, $m] = explode(':', $this->scheduled_time);
        $now  = now();
        $base = $now->copy()->setTime((int)$h, (int)$m, 0);

        return match ($this->frequency) {
            TaskFrequency::DAILY => $base->lte($now) ? $base->addDay() : $base,

            TaskFrequency::WEEKLY => (function () use ($base, $now) {
                $dow    = $this->day_of_week ?? 0; // 0=Mon
                $target = $base->copy()->startOfWeek()->addDays($dow)->setTime(...explode(':', $this->scheduled_time));
                while ($target->lte($now)) {
                    $target->addWeek();
                }
                return $target;
            })(),

            TaskFrequency::MONTHLY => (function () use ($base, $now) {
                $dom    = $this->day_of_month ?? 1;
                $target = $base->copy()->startOfMonth()->addDays($dom - 1)->setTime(...explode(':', $this->scheduled_time));
                while ($target->lte($now)) {
                    $target->addMonth();
                }
                return $target;
            })(),
        };
    }

    public function isDue(): bool
    {
        return $this->is_active
            && $this->next_run_at !== null
            && $this->next_run_at->lte(now());
    }

    public function targetLabel(): string
    {
        return match ($this->target_type) {
            'all_guardians'     => 'Tous les tuteurs',
            'class_guardians'   => 'Tuteurs — ' . ($this->targetClass?->name ?? '—'),
            'grade_guardians'   => 'Tuteurs — Niveau ' . ($this->targetGrade?->name ?? '—'),
            'unpaid_guardians'  => 'Tuteurs (factures impayées)',
            'overdue_guardians' => 'Tuteurs (factures en retard)',
            'school_admins'     => 'Administrateurs',
            default             => $this->target_type,
        };
    }

    public function frequencyLabel(): string
    {
        $base = $this->frequency->label() . ' à ' . $this->scheduled_time;

        if ($this->frequency === TaskFrequency::WEEKLY) {
            $days = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
            $base .= ' (' . ($days[$this->day_of_week ?? 0] ?? '') . ')';
        } elseif ($this->frequency === TaskFrequency::MONTHLY) {
            $base .= ' (jour ' . ($this->day_of_month ?? 1) . ')';
        }

        return $base;
    }
}
