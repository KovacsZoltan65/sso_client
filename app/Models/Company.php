<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $name Cég neve
 * @property string $code Cég kódja
 * @property string $email Cég email címe
 * @property string $phone Cég telefonszáma
 * @property string $address Cég címe
 * @property boolean $is_active Aktív
 */
class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'email',
        'phone',
        'address',
        'is_active',
    ];
    /**
     * A cég modell típuskonverzióinak meghatározása.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
