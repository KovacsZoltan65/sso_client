<?php

namespace App\Http\Requests\Users;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50])],
            'sort_field' => ['nullable', 'string', Rule::in(['id', 'sso_user_id', 'name', 'email', 'local_status', 'last_authenticated_at', 'created_at', 'updated_at'])],
            'sort_order' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'global' => ['nullable', 'string', 'max:255'],
            'local_status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
            'has_sso_link' => ['nullable', 'boolean'],
        ];
    }
}
