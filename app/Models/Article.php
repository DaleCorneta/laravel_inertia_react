<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['author_id', 'title', 'content', 'status', 'archived_at'])]
class Article extends Model
{
    use SoftDeletes;

    /** @var array<string, string> */
    protected $attributes = ['status' => 'draft'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'content' => 'array',
            'archived_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return HasMany<ArticleInlineMedia, $this> */
    public function inlineMedia(): HasMany
    {
        return $this->hasMany(ArticleInlineMedia::class);
    }
}
