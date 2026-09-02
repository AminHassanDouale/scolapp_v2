<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only audit trail for a report card's approval workflow.
 * One row per formal submit / approve / reject / publish action.
 */
class ReportCardApproval extends Model
{
    protected $fillable = [
        'report_card_id',
        'step',
        'action',
        'user_id',
        'comment',
    ];

    public function reportCard(): BelongsTo
    {
        return $this->belongsTo(ReportCard::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
