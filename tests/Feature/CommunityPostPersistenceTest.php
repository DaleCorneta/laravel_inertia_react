<?php

namespace Tests\Feature;

use App\Models\CommunityPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityPostPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_community_post_records_author_content_and_separate_archive_and_trash_states(): void
    {
        $author = User::factory()->create();
        $post = CommunityPost::create(['author_id' => $author->id, 'body' => 'A community update']);

        $this->assertSame($author->id, $post->author->id);
        $this->assertNull($post->archived_at);
        $post->forceFill(['archived_at' => now()])->save();
        $post->delete();

        $this->assertSoftDeleted('community_posts', ['id' => $post->id]);
        $this->assertNotNull($post->fresh()->archived_at);
        $this->assertNotNull($post->fresh()->deleted_at);
    }

    public function test_deleting_the_author_preserves_posts_with_a_null_author(): void
    {
        $author = User::factory()->create();
        $post = CommunityPost::create(['author_id' => $author->id, 'body' => 'Keep the post history']);
        $author->forceDelete();

        $this->assertDatabaseHas('community_posts', ['id' => $post->id, 'author_id' => null]);
        $this->assertNull($post->fresh()->author);
    }
}
