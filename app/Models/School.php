<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class School extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    // ── SaaS plans ───────────────────────────────────────────────────────────
    const PLAN_TRIAL      = 'trial';
    const PLAN_BASIC      = 'basic';       // ≤ 200 students, ≤ 20 teachers
    const PLAN_PRO        = 'pro';         // ≤ 1000 students, ≤ 100 teachers
    const PLAN_ENTERPRISE = 'enterprise';  // unlimited

    const PLAN_LIMITS = [
        self::PLAN_TRIAL      => ['students' => 30,   'teachers' => 5],
        self::PLAN_BASIC      => ['students' => 200,  'teachers' => 20],
        self::PLAN_PRO        => ['students' => 1000, 'teachers' => 100],
        self::PLAN_ENTERPRISE => ['students' => 0,    'teachers' => 0],   // 0 = unlimited
    ];

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'code',
        'logo',
        'address',
        'city',
        'country',
        'phone',
        'email',
        'website',
        'currency',
        'default_locale',
        'timezone',
        'date_format',
        'vat_rate',
        'settings',
        'is_active',
        'plan',
        'trial_ends_at',
        'subscription_ends_at',
        'max_students',
        'max_teachers',
        'contact_name',
        'suspension_reason',
        'suspended_at',
    ];

    protected function casts(): array
    {
        return [
            'vat_rate'             => 'decimal:4',
            'settings'             => 'array',
            'is_active'            => 'boolean',
            'trial_ends_at'        => 'datetime',
            'subscription_ends_at' => 'datetime',
            'suspended_at'         => 'datetime',
        ];
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    protected function logoUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->logo
                ? Storage::url($this->logo)
                : asset('images/logo_ScolApp.png'),
        );
    }

    protected function planLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => match ($this->plan) {
                self::PLAN_TRIAL      => 'Essai',
                self::PLAN_BASIC      => 'Basic',
                self::PLAN_PRO        => 'Pro',
                self::PLAN_ENTERPRISE => 'Enterprise',
                default               => ucfirst($this->plan ?? 'trial'),
            }
        );
    }

    // ── SaaS subscription methods ──────────────────────────────────────────────

    public function hasValidSubscription(): bool
    {
        // Enterprise & trial with no end date = always valid
        if ($this->plan === self::PLAN_ENTERPRISE) {
            return true;
        }

        if ($this->plan === self::PLAN_TRIAL) {
            return $this->trial_ends_at === null || $this->trial_ends_at->isFuture();
        }

        return $this->subscription_ends_at !== null && $this->subscription_ends_at->isFuture();
    }

    public function isOnTrial(): bool
    {
        return $this->plan === self::PLAN_TRIAL
            && ($this->trial_ends_at === null || $this->trial_ends_at->isFuture());
    }

    public function daysUntilExpiry(): ?int
    {
        if ($this->plan === self::PLAN_TRIAL && $this->trial_ends_at) {
            return max(0, (int) now()->diffInDays($this->trial_ends_at, false));
        }
        if ($this->subscription_ends_at) {
            return max(0, (int) now()->diffInDays($this->subscription_ends_at, false));
        }
        return null;
    }

    public function canAddStudent(): bool
    {
        $limit = $this->max_students > 0
            ? $this->max_students
            : (self::PLAN_LIMITS[$this->plan]['students'] ?? 30);

        return $limit === 0 || $this->students()->count() < $limit;
    }

    public function canAddTeacher(): bool
    {
        $limit = $this->max_teachers > 0
            ? $this->max_teachers
            : (self::PLAN_LIMITS[$this->plan]['teachers'] ?? 5);

        return $limit === 0 || $this->teachers()->count() < $limit;
    }

    public function suspend(string $reason): void
    {
        $this->update([
            'is_active'         => false,
            'suspension_reason' => $reason,
            'suspended_at'      => now(),
        ]);
    }

    public function reactivate(): void
    {
        $this->update([
            'is_active'         => true,
            'suspension_reason' => null,
            'suspended_at'      => null,
        ]);
    }

    public function upgradePlan(string $plan, int $months = 12): void
    {
        $this->update([
            'plan'                 => $plan,
            'subscription_ends_at' => now()->addMonths($months),
        ]);
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function academicYears(): HasMany
    {
        return $this->hasMany(AcademicYear::class);
    }

    public function academicCycles(): HasMany
    {
        return $this->hasMany(AcademicCycle::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function teachers(): HasMany
    {
        return $this->hasMany(Teacher::class);
    }

    public function guardians(): HasMany
    {
        return $this->hasMany(Guardian::class);
    }

    public function schoolClasses(): HasMany
    {
        return $this->hasMany(SchoolClass::class);
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class);
    }

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }
}
