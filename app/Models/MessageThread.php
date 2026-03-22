<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MessageThread extends Model
{
    use HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid', 'school_id', 'created_by', 'subject', 'is_archived',
    ];

    protected $casts = [
        'is_archived' => 'boolean',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Alias used by admin thread view
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'thread_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(MessageRecipient::class, 'thread_id');
    }

    // Alias used by portal views (messages list eager-loads 'participants.user')
    public function participants(): HasMany
    {
        return $this->hasMany(MessageRecipient::class, 'thread_id');
    }

    public function latestMessage(): HasMany
    {
        return $this->hasMany(Message::class, 'thread_id')->latest()->limit(1);
    }

    // Alias used by portal views (messages list eager-loads 'lastMessage')
    public function lastMessage(): HasMany
    {
        return $this->hasMany(Message::class, 'thread_id')->latest()->limit(1);
    }
}
