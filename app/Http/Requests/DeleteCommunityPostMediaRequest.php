<?php

namespace App\Http\Requests;

use App\Models\CommunityPost;
use App\Models\CommunityPostMedia;
use Illuminate\Foundation\Http\FormRequest;

class DeleteCommunityPostMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $post = $this->route('post');
        $media = $this->route('media');

        return $post instanceof CommunityPost
            && $media instanceof CommunityPostMedia
            && $media->community_post_id === $post->id
            && $post->author_id === $this->user()?->id
            && ($this->user()?->can('community-feed.update') ?? false);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [];
    }
}
