<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OidcLogoutReceipt extends Model
{
    protected $fillable = [
        'jti_hash',
        'issuer',
        'audience',
        'sid_hash',
        'outcome',
        'processed_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
