<?php

declare(strict_types=1);

namespace App\Http\Requests\Employees;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('employees.create') === true;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'employee_number' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('employees', 'employee_number')->where(
                    fn ($query) => $query->where('company_id', $this->integer('company_id'))
                ),
            ],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:100'],
            'position' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}