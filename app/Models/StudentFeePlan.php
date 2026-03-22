<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StudentFeePlan extends Model
{
    use BelongsToSchool, HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid',
        'school_id',
        'enrollment_id',
        'fee_schedule_id',
        'payment_frequency',
        'installments',
        'discount_amount',
        'discount_pct',
        'discount_reason',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'payment_frequency' => \App\Enums\FeeScheduleType::class,
            'discount_amount'   => 'decimal:2',
            'discount_pct'      => 'decimal:4',
            'is_active'         => 'boolean',
        ];
    }

    public function installments(): int
    {
        // Custom count takes priority; fall back to frequency default
        if ($this->installments !== null && $this->installments > 0) {
            return (int) $this->installments;
        }
        return $this->payment_frequency instanceof \App\Enums\FeeScheduleType
            ? $this->payment_frequency->installments()
            : 1;
    }

    public function installmentAmount(): float
    {
        // Only tuition fees go into installments; inscription fees are due immediately
        $total = $this->feeSchedule?->tuition_amount ?? 0;
        $n     = $this->installments();
        return $n > 0 ? round($total / $n) : $total;
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    protected function finalAmount(): Attribute
    {
        return Attribute::make(
            get: function () {
                // Discounts apply to tuition fees only (inscription is always fixed)
                $total = $this->feeSchedule?->tuition_amount ?? 0;

                // Apply percentage discount first, then fixed discount
                if ($this->discount_pct > 0) {
                    $total = $total - ($total * ($this->discount_pct / 100));
                }

                if ($this->discount_amount > 0) {
                    $total = $total - $this->discount_amount;
                }

                return max(0, $total);
            },
        );
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function feeSchedule(): BelongsTo
    {
        return $this->belongsTo(FeeSchedule::class);
    }
}
