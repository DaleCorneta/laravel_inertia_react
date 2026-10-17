<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['author_id', 'body', 'archived_at'])]
class CommunityPost extends Model
{
    use SoftDeletes;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['archived_at' => 'datetime', 'moderated_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return HasMany<CommunityComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(CommunityComment::class, 'community_post_id');
    }

    /** @return HasMany<CommunityPostLike, $this> */
    public function likes(): HasMany
    {
        return $this->hasMany(CommunityPostLike::class, 'community_post_id');
    }

    /** @return HasMany<CommunityPostMedia, $this> */
    public function media(): HasMany
    {
        return $this->hasMany(CommunityPostMedia::class, 'community_post_id');
    }
}
