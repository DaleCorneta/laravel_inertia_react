<?php

namespace App\Http\Requests;

use App\Models\Article;
use App\Rules\ArticleDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateArticleRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => is_string($this->input('title')) ? trim($this->input('title')) : $this->input('title'),
        ]);
    }

    public function authorize(): bool
    {
        return $this->user()?->can('articles.update') ?? false;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $article = $this->route('article');
            $content = $this->input('content');
            if ($validator->errors()->isNotEmpty() || ! $article instanceof Article || ! is_array($content)) {
                return;
            }

            $ids = [];
            $collect = function (array $nodes) use (&$collect, &$ids): void {
                foreach ($nodes as $node) {
                    if (($node['type'] ?? null) === 'articleImage') {
                        $ids[] = $node['attrs']['mediaId'];
                    }
                    if (is_array($node['content'] ?? null)) {
                        $collect($node['content']);
                    }
                }
            };
            $collect($content['content'] ?? []);
            $ids = array_values(array_unique($ids));

            if ($ids !== [] && $article->inlineMedia()->whereIn('id', $ids)->count() !== count($ids)) {
                $validator->errors()->add('content', 'The article content references an image that does not belong to this article.');
            }
        });
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'content' => ['required', 'array', new ArticleDocument],
            'cover_image' => ['nullable', 'prohibited_if:remove_cover_image,true', 'image', 'mimes:jpg,jpeg,png', 'extensions:jpg,jpeg,png', 'max:4096', 'dimensions:max_width=6000,max_height=6000'],
            'remove_cover_image' => ['sometimes', 'boolean'],
        ];
    }
}
