<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['prefix', 'first_name', 'middle_name', 'last_name', 'suffix'] as $field) {
            if (is_string($this->input($field))) {
                $normalized[$field] = trim($this->input($field));
            }
        }

        if (is_string($this->input('email'))) {
            $normalized['email'] = mb_strtolower(trim($this->input('email')));
        }

        $this->merge($normalized);
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'prefix' => ['nullable', 'string', 'max:20'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user())],
            'current_password' => ['required', 'current_password'],
        ];
    }
}
