<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShareLink extends Model
{
    protected $fillable = [
        'vault_file_id',
        'user_id',
        'token',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function file()
    {
        return $this->belongsTo(VaultFile::class, 'vault_file_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}