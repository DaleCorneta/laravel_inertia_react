<?php

namespace App\Http\Requests;

use App\Models\CommunityPost;
use App\Services\CommunityPostMediaService;
use Illuminate\Foundation\Http\FormRequest;

class StoreCommunityPostMediaRequest extends FormRequest
{
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
        return [
            'media' => ['required', 'array', 'min:1', 'max:4'],
            'media.*' => ['file', 'image', 'mimes:jpg,jpeg,png', 'extensions:jpg,jpeg,png', 'max:10240'],
        ];
    }

    protected function passedValidation(): void
    {
        app(CommunityPostMediaService::class)->inspect($this->file('media', []));
    }
}
