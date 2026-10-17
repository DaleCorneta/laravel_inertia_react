<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyMfaActionRequest extends FormRequest
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
            'code' => ['required', 'string', 'alpha_num', 'max:32'],
        ];
    }
}
