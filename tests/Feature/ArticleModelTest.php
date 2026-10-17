<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_article_keeps_structured_content_and_author_relationship(): void
    {
        $author = User::factory()->create();
        $content = [
            'type' => 'doc',
            'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Hello']]]],
        ];

        $article = Article::create([
            'author_id' => $author->id,
            'title' => 'A draft',
            'content' => $content,
        ]);

        $this->assertSame($content, $article->fresh()->content);
        $this->assertSame('draft', $article->status);
        $this->assertTrue($article->author->is($author));
        $this->assertTrue($author->articles()->whereKey($article->id)->exists());
    }

    public function test_archived_articles_remain_distinct_from_soft_deleted_articles(): void
    {
        $article = Article::create(['title' => 'Archived draft']);
        $article->forceFill(['archived_at' => now()])->save();

        $this->assertNotNull($article->fresh()->archived_at);
        $this->assertFalse($article->trashed());

        $article->delete();

        $this->assertTrue($article->trashed());
        $this->assertNotNull(Article::withTrashed()->findOrFail($article->id)->archived_at);
    }
}
