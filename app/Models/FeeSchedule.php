<?php

namespace App\Models;

use App\Enums\FeeScheduleType;
use App\Traits\BelongsToSchool;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FeeSchedule extends Model
{
    use BelongsToSchool, HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid',
        'school_id',
        'academic_year_id',
        'grade_id',
        'name',
        'schedule_type',
        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'schedule_type' => FeeScheduleType::class,
            'is_default'    => 'boolean',
            'is_active'     => 'boolean',
        ];
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    protected function totalAmount(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->feeItems->sum(fn ($item) => $item->pivot->amount ?? 0),
        );
    }

    /** Sum of tuition/recurring fees only (excludes INSCR — registration fees) */
    protected function tuitionAmount(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->feeItems
                ->filter(fn ($item) => strtoupper($item->code ?? '') !== 'INSCR')
                ->sum(fn ($item) => $item->pivot->amount ?? 0),
        );
    }

    /** Sum of registration fees only (INSCR items — due immediately, not in barème) */
    protected function inscriptionAmount(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->feeItems
                ->filter(fn ($item) => strtoupper($item->code ?? '') === 'INSCR')
                ->sum(fn ($item) => $item->pivot->amount ?? 0),
        );
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function feeItems(): BelongsToMany
    {
        return $this->belongsToMany(FeeItem::class, 'fee_schedule_items')
            ->withPivot('amount')
            ->withTimestamps();
    }

    public function studentFeePlans(): HasMany
    {
        return $this->hasMany(StudentFeePlan::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
