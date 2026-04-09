<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToSchool;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use Auditable, BelongsToSchool, HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid',
        'school_id',
        'academic_year_id',
        'created_by',
        'reference',
        'category',
        'label',
        'amount',
        'expense_date',
        'payment_method',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount'       => 'decimal:2',
            'expense_date' => 'date',
        ];
    }

    public static function categoryLabel(string $category): string
    {
        return match($category) {
            'salaires'    => 'Salaires',
            'loyer'       => 'Loyer / Local',
            'fournitures' => 'Fournitures',
            'services'    => 'Services & Utilities',
            'maintenance' => 'Maintenance',
            'autre'       => 'Autre',
            default       => ucfirst($category),
        };
    }

    public static function categories(): array
    {
        return [
            'salaires', 'loyer', 'fournitures', 'services', 'maintenance', 'autre',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────────

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
