<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => is_string($this->input('email')) ? mb_strtolower(trim($this->input('email'))) : $this->input('email'),
        ]);
    }

    public function authorize(): bool
    {
        return Gate::allows('users.create')
            && (empty($this->input('role_ids')) || $this->user()->can('roles.manage'));
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $rules = [
            'prefix' => ['nullable', 'string', 'max:20'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ];

        if ($this->user()->can('roles.manage')) {
            $rules['role_ids'] = ['sometimes', 'array', 'max:100'];
            $rules['role_ids.*'] = ['required', 'integer', 'distinct', Rule::exists('roles', 'id')->where('guard_name', 'web')];
        }

        return $rules;
    }
}
