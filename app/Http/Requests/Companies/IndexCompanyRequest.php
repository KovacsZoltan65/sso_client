<?php

namespace App\Http\Requests\Companies;

use App\Models\Company;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Company::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort_field' => ['nullable', 'string', Rule::in(['name', 'code', 'email', 'is_active', 'created_at'])],
            'sort_order' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        if ($this->has('is_active')) {
            $rawValue = $this->input('is_active');
            $normalized['is_active'] = $rawValue === '' || $rawValue === null
                ? null
                : filter_var($rawValue, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        }

        if ($this->filled('sort_order')) {
            $normalized['sort_order'] = strtolower((string) $this->input('sort_order'));
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }
}
