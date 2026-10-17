<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name')]);
    }

    public function authorize(): bool
    {
        return $this->user()?->can('roles.manage') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', 'regex:/^[\pL\pM\pN _.-]+$/u', Rule::unique('roles', 'name')->where('guard_name', 'web')],
            'permission_ids' => ['sometimes', 'array', 'max:100'],
            'permission_ids.*' => ['required', 'integer', 'distinct', Rule::exists('permissions', 'id')->where('guard_name', 'web')],
        ];
    }
}
