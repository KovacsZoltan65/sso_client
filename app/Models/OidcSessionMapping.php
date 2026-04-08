<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OidcSessionMapping extends Model
{
    protected $fillable = [
        'sid_hash',
        'session_id',
        'user_id',
        'issuer',
        'client_id',
        'bound_at',
        'last_seen_at',
        'invalidated_at',
    ];

    protected function casts(): array
    {
        return [
            'bound_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'invalidated_at' => 'datetime',
        ];
    }
}
