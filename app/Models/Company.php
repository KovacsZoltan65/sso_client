<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

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
    use LogsActivity;

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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('client.admin.company')
            ->logOnly([
                'name',
                'code',
                'email',
                'phone',
                'address',
                'is_active',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
