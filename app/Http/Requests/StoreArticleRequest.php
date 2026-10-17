<?php

namespace App\Http\Requests;

use App\Rules\ArticleDocument;
use Illuminate\Foundation\Http\FormRequest;

class StoreArticleRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => is_string($this->input('title')) ? trim($this->input('title')) : $this->input('title'),
        ]);
    }

    public function authorize(): bool
    {
        return $this->user()?->can('articles.create') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'content' => ['required', 'array', new ArticleDocument],
            'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'extensions:jpg,jpeg,png', 'max:4096', 'dimensions:max_width=6000,max_height=6000'],
        ];
    }
}
