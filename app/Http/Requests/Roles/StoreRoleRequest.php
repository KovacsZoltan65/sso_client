<?php

namespace App\Http\Requests\Roles;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Role;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Role::class) ?? false;
    }

    public function rules(): array
    {
        $guardName = (string) ($this->input('guard_name') ?? 'web');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique(config('permission.table_names.roles'), 'name')
                    ->where(fn ($query) => $query->where('guard_name', $guardName)),
            ],
            'guard_name' => ['required', 'string', Rule::in(['web'])],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['integer', Rule::exists(config('permission.table_names.permissions'), 'id')],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'guard_name' => $this->input('guard_name', 'web'),
            'permission_ids' => array_values(array_filter((array) $this->input('permission_ids', []), fn ($value) => $value !== null && $value !== '')),
        ]);
    }
}
