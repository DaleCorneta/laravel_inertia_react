<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class ContactRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $values = [];
        foreach (['prefix', 'first_name', 'middle_name', 'last_name', 'suffix', 'organization', 'title', 'email', 'phone', 'notes'] as $field) {
            if (is_string($this->input($field))) {
                $values[$field] = trim($this->input($field));
            }
        }
        if (isset($values['email'])) {
            $values['email'] = mb_strtolower($values['email']);
        }
        $this->merge($values);
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
            'organization' => ['nullable', 'string', 'max:160'],
            'title' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'attachments' => ['sometimes', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:10240', 'extensions:pdf,png,jpg,jpeg', 'mimetypes:application/pdf,image/png,image/jpeg'],
        ];
    }
}
