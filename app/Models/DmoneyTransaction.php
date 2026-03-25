<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DmoneyTransaction extends Model
{
    protected $fillable = [
        'uuid',
        'school_id',
        'invoice_id',
        'student_id',
        'user_id',
        'billing_subscription_id',
        'order_id',
        'checkout_url',
        'amount',
        'currency',
        'status',
        'guardian_phone',
        'webhook_payload',
        'completed_at',
        'cancelled_at',
        'payment_id',
    ];

    protected $casts = [
        'webhook_payload' => 'array',
        'completed_at'    => 'datetime',
        'cancelled_at'    => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(fn ($m) => $m->uuid ??= (string) Str::uuid());
    }

    // ── Relations ──────────────────────────────────────────────────────────────

    public function school(): BelongsTo    { return $this->belongsTo(School::class); }
    public function invoice(): BelongsTo   { return $this->belongsTo(Invoice::class); }
    public function student(): BelongsTo   { return $this->belongsTo(Student::class); }
    public function user(): BelongsTo      { return $this->belongsTo(User::class); }
    public function payment(): BelongsTo   { return $this->belongsTo(Payment::class); }

    // ── Status helpers ─────────────────────────────────────────────────────────

    public function isPending(): bool    { return $this->status === 'pending'; }
    public function isCompleted(): bool  { return $this->status === 'completed'; }
    public function isFailed(): bool     { return $this->status === 'failed'; }
    public function isCancelled(): bool  { return $this->status === 'cancelled'; }

    public function statusColor(): string
    {
        return match ($this->status) {
            'completed' => 'success',
            'failed'    => 'error',
            'cancelled' => 'ghost',
            default     => 'warning',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'completed' => 'Confirmé',
            'failed'    => 'Échoué',
            'cancelled' => 'Annulé',
            default     => 'En attente',
        };
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopePending($q)    { return $q->where('status', 'pending'); }
    public function scopeCompleted($q)  { return $q->where('status', 'completed'); }
}
