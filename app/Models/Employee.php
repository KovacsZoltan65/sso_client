<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $company_id Ceg azonosito
 * @property string $employee_number Dolgozo azonosito
 * @property string $name Dolgozo nev
 * @property string|null $email Dolgozo email cime
 * @property string|null $phone Dolgozo telefonszama
 * @property string|null $position Dolgozo pozicioja
 * @property bool $is_active Aktiv
 */
class Employee extends Model
{
    use HasFactory;
    use LogsActivity;
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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('client.admin.employee')
            ->logOnly([
                'company_id',
                'employee_number',
                'name',
                'email',
                'phone',
                'position',
                'is_active',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
