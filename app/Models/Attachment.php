<?php

namespace App\Models;

use App\Enums\AttachmentCategory;
use App\Traits\BelongsToSchool;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Attachment extends Model
{
    use BelongsToSchool, HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid',
        'school_id',
        'attachable_type',
        'attachable_id',
        'category',
        'label',
        'disk',
        'path',
        'original_name',
        'size',
        'mime_type',
        'expires_at',
        'notes',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'category'   => AttachmentCategory::class,
            'expires_at' => 'date',
            'size'       => 'integer',
        ];
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function url(): string
    {
        return route('attachments.download', $this->uuid);
    }

    public function humanSize(): string
    {
        $bytes = $this->size;
        if ($bytes < 1024)       return $bytes . ' B';
        if ($bytes < 1048576)    return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1048576, 1) . ' MB';
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type ?? '', 'image/');
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isExpiringSoon(): bool
    {
        return $this->expires_at && ! $this->isExpired()
            && $this->expires_at->diffInDays(now()) <= 30;
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
