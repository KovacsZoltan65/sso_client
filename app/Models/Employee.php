<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $company_id Cég azonosító
 * @property string $employee_number Dolgozó azonosító
 * @property string $name Dolgozó név
 * @property string $email Dolgozó email címe
 * @property string $phone Dolgozó telefonszáma
 * @property string $position Dolgozó pozíciója
 * @property boolean $is_active Aktív
 */
class Employee extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'employee_number',
        'name',
        'email',
        'phone',
        'position',
        'is_active',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}