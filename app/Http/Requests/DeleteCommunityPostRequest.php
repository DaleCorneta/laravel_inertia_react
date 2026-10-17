<?php

namespace App\Http\Requests;

use App\Models\CommunityPost;
use Illuminate\Foundation\Http\FormRequest;

class DeleteCommunityPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        $post = $this->route('post');

        return $post instanceof CommunityPost
            && $post->author_id === $this->user()?->id
            && ($this->user()?->can('community-feed.delete') ?? false);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [];
    }
}
