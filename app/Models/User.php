<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable([
    'sso_user_id',
    'name',
    'email',
    'password',
    'email_verified_at',
    'local_status',
    'fallback_auth_enabled',
    'notes',
    'last_authenticated_at',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;
    use HasRoles;
    use Notifiable;

    protected string $guard_name = 'web';

    /**
     * A natív típuskonverziók meghatározása a biztonságos attribútumkezeléshez.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_authenticated_at' => 'datetime',
            'password' => 'hashed',
            'fallback_auth_enabled' => 'boolean',
        ];
    }

}
