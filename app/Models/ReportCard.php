<?php

namespace App\Models;

use App\Enums\ReportCardStatus;
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
        'status',
        'average',
        'average_manual',
        'total_manual',
        'class_average',
        'class_average_manual',
        'rank',
        'class_size',
        'general_comment',
        'teacher_comment',
        'discipline_status',
        'is_published',
        'published_at',
        'submitted_by',
        'submitted_at',
        'pedagogie_approved_by',
        'pedagogie_approved_at',
        'finance_approved_by',
        'finance_approved_at',
        'direction_approved_by',
        'direction_approved_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'period'               => ReportPeriod::class,
            'status'               => ReportCardStatus::class,
            'average'              => 'decimal:2',
            'average_manual'       => 'decimal:2',
            'total_manual'         => 'decimal:2',
            'class_average'        => 'decimal:2',
            'class_average_manual' => 'decimal:2',
            'rank'                 => 'integer',
            'class_size'           => 'integer',
            'is_published'         => 'boolean',
            'published_at'         => 'datetime',
            'submitted_at'         => 'datetime',
            'pedagogie_approved_at'=> 'datetime',
            'finance_approved_at'  => 'datetime',
            'direction_approved_at'=> 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Effective values — manual overrides win over computed ones (spec §2)
    // -------------------------------------------------------------------------

    public function effectiveAverage(): ?float
    {
        $v = $this->average_manual ?? $this->average;
        return $v !== null ? (float) $v : null;
    }

    public function effectiveClassAverage(): ?float
    {
        $v = $this->class_average_manual ?? $this->class_average;
        return $v !== null ? (float) $v : null;
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

    public function approvals(): HasMany
    {
        return $this->hasMany(ReportCardApproval::class)->latest();
    }

    public function scopeStatus(Builder $query, ReportCardStatus|string $status): Builder
    {
        return $query->where('status', $status instanceof ReportCardStatus ? $status->value : $status);
    }

    // -------------------------------------------------------------------------
    // Workflow transitions
    // -------------------------------------------------------------------------

    /** Current status as an enum, tolerating a null column on legacy rows. */
    public function statusEnum(): ReportCardStatus
    {
        return $this->status instanceof ReportCardStatus
            ? $this->status
            : (ReportCardStatus::tryFrom((string) $this->status) ?? ReportCardStatus::DRAFT);
    }

    /** Whether the given user may advance the card from its current status. */
    public function canAdvance(?User $user): bool
    {
        return $user !== null && $user->hasAnyRole($this->statusEnum()->rolesToAdvance());
    }

    public function canReject(?User $user): bool
    {
        return $user !== null
            && $this->statusEnum()->canReject()
            && $user->hasAnyRole($this->statusEnum()->rolesToAdvance());
    }

    /**
     * Advance the workflow by one step, writing an audit row and stamping the
     * relevant approver field. Publishing also flips is_published for the
     * guardian-facing views. Returns the new status.
     */
    public function advance(User $actor, ?string $comment = null): ReportCardStatus
    {
        $current = $this->statusEnum();
        $next    = $current->next();
        if ($next === null) {
            return $current;
        }

        $stamp = match ($current) {
            ReportCardStatus::DRAFT, ReportCardStatus::REJECTED => ['submitted_by' => $actor->id, 'submitted_at' => now()],
            ReportCardStatus::SUBMITTED          => ['pedagogie_approved_by' => $actor->id, 'pedagogie_approved_at' => now()],
            ReportCardStatus::PEDAGOGIE_APPROVED => ['finance_approved_by' => $actor->id, 'finance_approved_at' => now()],
            ReportCardStatus::FINANCE_APPROVED   => ['direction_approved_by' => $actor->id, 'direction_approved_at' => now()],
            default                              => [],
        };

        if ($next === ReportCardStatus::PUBLISHED) {
            $stamp['is_published'] = true;
            $stamp['published_at'] = now();
        }

        $this->update(array_merge(['status' => $next->value, 'rejection_reason' => null], $stamp));

        $this->approvals()->create([
            'step'    => $current->value,
            'action'  => $next === ReportCardStatus::PUBLISHED ? 'published'
                       : ($next === ReportCardStatus::SUBMITTED ? 'submitted' : 'approved'),
            'user_id' => $actor->id,
            'comment' => $comment,
        ]);

        return $next;
    }

    /** Reject the card back to draft-editable, recording the reason. */
    public function reject(User $actor, string $reason): void
    {
        $current = $this->statusEnum();

        $this->update([
            'status'           => ReportCardStatus::REJECTED->value,
            'rejection_reason' => $reason,
            'is_published'     => false,
            'published_at'     => null,
        ]);

        $this->approvals()->create([
            'step'    => $current->value,
            'action'  => 'rejected',
            'user_id' => $actor->id,
            'comment' => $reason,
        ]);
    }
}
