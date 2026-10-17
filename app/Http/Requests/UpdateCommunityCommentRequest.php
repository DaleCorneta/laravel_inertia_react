<?php

namespace App\Http\Requests;

use App\Models\CommunityComment;
use App\Models\CommunityPost;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCommunityCommentRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['body' => is_string($this->input('body')) ? trim($this->input('body')) : $this->input('body')]);
    }

    public function authorize(): bool
    {
        $post = $this->route('post');
        $comment = $this->route('comment');

        return $post instanceof CommunityPost
            && $comment instanceof CommunityComment
            && $comment->community_post_id === $post->id
            && $comment->author_id === $this->user()?->id
            && ($this->user()?->can('community-feed.update_comment') ?? false);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return ['body' => ['required', 'string', 'max:2000']];
    }
}
