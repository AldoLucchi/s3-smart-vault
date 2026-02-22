<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VaultFile extends Model
{
    protected $fillable = [
        'user_id',
        'original_name',
        's3_key',
        'size',
        'storage_class',
        'mime_type',
        'restoration_status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function shareLinks()
    {
        return $this->hasMany(ShareLink::class);
    }
}