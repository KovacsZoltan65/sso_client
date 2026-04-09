<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Company;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property int $company_id
 * @property Company $company
 * @property string $employee_number
 * @property string $name
 * @property string $email
 * @property string $phone
 * @property string $position
 * @property boolean $is_active
 * @property DateTime $created_at
 * @property DateTime $updated_at
 */
class EmployeeResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array{
     *      company_id: mixed, 
     *      company_name: mixed, 
     *      created_at: mixed, 
     *      email: mixed, 
     *      employee_number: mixed, 
     *      id: mixed, 
     *      is_active: mixed, 
     *      name: mixed, 
     *      phone: mixed, 
     *      position: mixed, 
     *      updated_at: mixed
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'company_name' => $this->company?->name,
            'employee_number' => $this->employee_number,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'position' => $this->position,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}