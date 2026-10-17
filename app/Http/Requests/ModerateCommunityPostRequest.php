<?php

namespace App\Http\Requests;

use App\Models\CommunityPost;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ModerateCommunityPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('post') instanceof CommunityPost
            && ($this->user()?->can('community-feed.moderate_posts') ?? false);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return ['action' => ['required', Rule::in(['hide', 'restore'])]];
    }
}
