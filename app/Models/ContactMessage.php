<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    protected $fillable = [
        'name',
        'email',
        'school',
        'phone',
        'message',
        'status',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];
}
