<?php

namespace App\Http\Requests;

use App\Models\CommunityPost;
use Illuminate\Foundation\Http\FormRequest;

class LikeCommunityPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        $post = $this->route('post');

        return $post instanceof CommunityPost
            && $post->archived_at === null
            && ($this->user()?->can('community-feed.like') ?? false);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [];
    }
}
