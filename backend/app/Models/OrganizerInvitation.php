<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizerInvitation extends Model
{
    protected $fillable = [
        'token',
        'created_by',
        'expires_at',
        'used_at',
        'used_by',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at'    => 'datetime',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function usedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'used_by');
    }

    public function isValid(): bool
    {
        return is_null($this->used_at) && now()->isBefore($this->expires_at);
    }
}
