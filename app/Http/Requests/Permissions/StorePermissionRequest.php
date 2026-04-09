<?php

namespace App\Http\Requests\Permissions;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Permission;

class StorePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Permission::class) ?? false;
    }

    public function rules(): array
    {
        $guardName = (string) ($this->input('guard_name') ?? 'web');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique(config('permission.table_names.permissions'), 'name')
                    ->where(fn ($query) => $query->where('guard_name', $guardName)),
            ],
            'guard_name' => ['required', 'string', Rule::in(['web'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'guard_name' => $this->input('guard_name', 'web'),
        ]);
    }
}
