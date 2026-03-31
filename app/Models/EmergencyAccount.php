<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'username',
    'password',
    'role',
    'is_active',
    'last_used_at',
    'expires_at',
    'allowed_ips',
    'notes',
])]
#[Hidden(['password', 'remember_token'])]
class EmergencyAccount extends Authenticatable
{
    use HasFactory;
    use Notifiable;

    protected string $guard_name = 'emergency';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'bool',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'allowed_ips' => 'array',
            'password' => 'hashed',
        ];
    }
}
