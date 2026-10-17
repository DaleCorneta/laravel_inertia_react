<?php

namespace App\Http\Requests;

use App\Models\CommunityPost;
use Illuminate\Foundation\Http\FormRequest;

class StoreCommunityCommentRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['body' => is_string($this->input('body')) ? trim($this->input('body')) : $this->input('body')]);
    }

    public function authorize(): bool
    {
        $post = $this->route('post');

        return $post instanceof CommunityPost
            && $post->archived_at === null
            && $post->moderated_at === null
            && ($this->user()?->can('community-feed.comment') ?? false);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return ['body' => ['required', 'string', 'max:2000']];
    }
}
