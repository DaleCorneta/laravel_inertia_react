<?php

namespace App\Http\Requests;

use App\Services\CommunityPostMediaService;
use Illuminate\Foundation\Http\FormRequest;

class StoreCommunityPostRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['body' => is_string($this->input('body')) ? trim($this->input('body')) : $this->input('body')]);
    }

    public function authorize(): bool
    {
        return $this->user()?->can('community-feed.create') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
            'media' => ['nullable', 'array', 'max:4'],
            'media.*' => ['file', 'image', 'mimes:jpg,jpeg,png', 'extensions:jpg,jpeg,png', 'max:10240'],
        ];
    }

    protected function passedValidation(): void
    {
        app(CommunityPostMediaService::class)->inspect($this->file('media', []));
    }
}
