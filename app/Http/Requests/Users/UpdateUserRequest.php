<?php

namespace App\Http\Requests\Users;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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
            'local_status' => ['required', 'string', Rule::in(['active', 'inactive'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
