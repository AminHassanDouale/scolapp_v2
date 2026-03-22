<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Traits\Auditable;
use App\Traits\BelongsToSchool;
use App\Traits\HasReference;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use Auditable, BelongsToSchool, HasFactory, HasReference, HasUuid, SoftDeletes;

    public const REFERENCE_PREFIX = 'PAY';

    protected $fillable = [
        'uuid',
        'reference',
        'school_id',
        'student_id',
        'enrollment_id',
        'received_by',
        'status',
        'payment_method',
        'amount',
        'payment_date',
        'transaction_ref',
        'bank_name',
        'check_number',
        'notes',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'status'       => PaymentStatus::class,
            'amount'       => 'decimal:2',
            'payment_date' => 'date',
            'meta'         => 'array',
        ];
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

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function invoices(): BelongsToMany
    {
        return $this->belongsToMany(Invoice::class, 'payment_allocations')
            ->withPivot('amount')
            ->withTimestamps();
    }
}
