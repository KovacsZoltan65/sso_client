<?php

namespace App\Http\Requests\Permissions;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;

class UpdatePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $permission = $this->route('permission');

        return $permission instanceof Permission
            ? ($this->user()?->can('update', $permission) ?? false)
            : false;
    }

    public function rules(): array
    {
        /** @var Permission $permission */
        $permission = $this->route('permission');
        $guardName = (string) ($this->input('guard_name') ?? $permission->guard_name ?? 'web');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique(config('permission.table_names.permissions'), 'name')
                    ->ignore($permission->id)
                    ->where(fn ($query) => $query->where('guard_name', $guardName)),
            ],
            'guard_name' => ['required', 'string', Rule::in(['web'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        /** @var Permission|null $permission */
        $permission = $this->route('permission');

        $this->merge([
            'guard_name' => $this->input('guard_name', $permission?->guard_name ?? 'web'),
        ]);
    }
}
