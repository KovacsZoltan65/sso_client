<?php

namespace App\Http\Requests\Emergency;

use Illuminate\Foundation\Http\FormRequest;

class EmergencyDeactivateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
            'operator' => ['nullable', 'string', 'max:255'],
        ];
    }
}
