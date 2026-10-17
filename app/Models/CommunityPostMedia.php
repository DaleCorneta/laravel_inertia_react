<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['community_post_id', 'uploaded_by', 'original_name', 'path', 'mime_type', 'size'])]
class CommunityPostMedia extends Model
{
    /** @return BelongsTo<CommunityPost, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(CommunityPost::class, 'community_post_id');
    }
}
