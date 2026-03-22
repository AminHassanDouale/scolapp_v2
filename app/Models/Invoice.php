<?php

namespace App\Models;

use App\Enums\FeeScheduleType;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
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
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use Auditable, BelongsToSchool, HasAttachments, HasFactory, HasReference, HasUuid, SoftDeletes;

    public const REFERENCE_PREFIX = 'INV';

    protected $fillable = [
        'uuid',
        'reference',
        'school_id',
        'student_id',
        'enrollment_id',
        'academic_year_id',
        'fee_schedule_id',
        'invoice_type',
        'schedule_type',
        'status',
        'issue_date',
        'due_date',
        'subtotal',
        'vat_rate',
        'vat_amount',
        'total',
        'paid_total',
        'balance_due',
        'penalty_amount',
        'installment_number',
        'notes',
        'meta',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'invoice_type'       => InvoiceType::class,
            'schedule_type'      => FeeScheduleType::class,
            'status'             => InvoiceStatus::class,
            'issue_date'         => 'date',
            'due_date'           => 'date',
            'subtotal'           => 'decimal:2',
            'vat_rate'           => 'decimal:4',
            'vat_amount'         => 'decimal:2',
            'total'              => 'decimal:2',
            'paid_total'         => 'decimal:2',
            'balance_due'        => 'decimal:2',
            'penalty_amount'     => 'decimal:2',
            'installment_number' => 'integer',
            'meta'               => 'array',
            'sent_at'            => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    protected function isOverdue(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->due_date !== null
                && $this->due_date->isPast()
                && ! in_array($this->status, [InvoiceStatus::PAID, InvoiceStatus::CANCELLED]),
        );
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeOverdue(Builder $query): Builder
    {
        return $query
            ->whereNotIn('status', [InvoiceStatus::PAID->value, InvoiceStatus::CANCELLED->value])
            ->whereDate('due_date', '<', now());
    }

    public function scopeDueSoon(Builder $query, int $days = 7): Builder
    {
        return $query
            ->whereNotIn('status', [InvoiceStatus::PAID->value, InvoiceStatus::CANCELLED->value])
            ->whereBetween('due_date', [now()->toDateString(), now()->addDays($days)->toDateString()]);
    }

    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            InvoiceStatus::PAID->value,
            InvoiceStatus::CANCELLED->value,
        ]);
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

    public function feeSchedule(): BelongsTo
    {
        return $this->belongsTo(FeeSchedule::class);
    }

    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function payments(): BelongsToMany
    {
        return $this->belongsToMany(Payment::class, 'payment_allocations')
            ->withPivot('amount')
            ->withTimestamps();
    }
}
