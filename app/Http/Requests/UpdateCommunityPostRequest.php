<?php

namespace App\Http\Requests;

use App\Models\CommunityPost;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCommunityPostRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['body' => is_string($this->input('body')) ? trim($this->input('body')) : $this->input('body')]);
    }

    public function authorize(): bool
    {
        $post = $this->route('post');

        return $post instanceof CommunityPost
            && $post->author_id === $this->user()?->id
            && ($this->user()?->can('community-feed.update') ?? false);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return ['body' => ['required', 'string', 'max:5000']];
    }
}
