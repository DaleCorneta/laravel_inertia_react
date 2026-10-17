<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmMfaEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password'],
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
        ];
    }
}
