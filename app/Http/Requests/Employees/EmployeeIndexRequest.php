<?php

declare(strict_types=1);

namespace App\Http\Requests\Employees;

use Illuminate\Foundation\Http\FormRequest;

class EmployeeIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('employees.view') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function validatedFilters(): array
    {
        $validated = $this->validated();

        return [
            'global' => $validated['global'] ?? null,
            'company_id' => isset($validated['company_id']) ? (int) $validated['company_id'] : null,
            'status' => $validated['status'] ?? null,
        ];
    }

    public function rules(): array
    {
        return [
            'global' => ['nullable', 'string', 'max:255'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'status' => ['nullable', 'in:true,false,1,0'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort_field' => ['nullable', 'string', 'in:name,email,position,employee_number,is_active,created_at'],
            'sort_order' => ['nullable', 'integer', 'in:1,-1'],
        ];
    }
}