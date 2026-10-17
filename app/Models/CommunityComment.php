<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['community_post_id', 'parent_comment_id', 'author_id', 'body'])]
class CommunityComment extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['moderated_at' => 'datetime'];
    }

    /** @return BelongsTo<CommunityComment, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(CommunityComment::class, 'parent_comment_id');
    }

    /** @return HasMany<CommunityComment, $this> */
    public function replies(): HasMany
    {
        return $this->hasMany(CommunityComment::class, 'parent_comment_id')
            ->orderBy('created_at')
            ->orderBy('id');
    }

    /** @return BelongsTo<CommunityPost, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(CommunityPost::class, 'community_post_id');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
