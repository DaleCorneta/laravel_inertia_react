<?php

namespace App\Http\Requests;

use App\Models\CommunityComment;
use App\Models\CommunityPost;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ModerateCommunityCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $post = $this->route('post');
        $comment = $this->route('comment');

        return $post instanceof CommunityPost
            && $comment instanceof CommunityComment
            && $comment->community_post_id === $post->id
            && ($this->user()?->can('community-feed.moderate_comments') ?? false);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return ['action' => ['required', Rule::in(['hide', 'restore'])]];
    }
}
