<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateUserStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('users.update');
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'is_active' => ['required', 'boolean'],
        ];
    }
}
