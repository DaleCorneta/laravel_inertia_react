<?php

namespace Tests\Feature;

use App\Models\CommunityComment;
use App\Models\CommunityPost;
use App\Models\CommunityPostMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CommunityFeedIndexTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, array{env: string|null, server: string|null, process: string|false}> */
    private array $originalFlags = [];

    protected function setUp(): void
    {
        foreach (['DEMO_MODULES_ENABLED', 'DEMO_COMMUNITY_FEED_ENABLED'] as $key) {
            $this->originalFlags[$key] = ['env' => $_ENV[$key] ?? null, 'server' => $_SERVER[$key] ?? null, 'process' => getenv($key)];
            $_ENV[$key] = $_SERVER[$key] = 'true';
            putenv($key.'=true');
        }
        Env::enablePutenv();
        $this->refreshApplication();
        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        foreach ($this->originalFlags as $key => $values) {
            foreach (['env' => '_ENV', 'server' => '_SERVER'] as $source => $target) {
                if ($values[$source] === null) {
                    unset($GLOBALS[$target][$key]);
                } else {
                    $GLOBALS[$target][$key] = $values[$source];
                }
            }
            $values['process'] === false ? putenv($key) : putenv($key.'='.$values['process']);
        }
        Env::enablePutenv();
    }

    public function test_community_feed_requires_authentication_and_view_permission(): void
    {
        $this->get('/community')->assertRedirect('/login');

        $user = User::factory()->create();
        $this->actingAs($user)->get('/community')->assertForbidden();
    }

    public function test_authorized_user_sees_recent_active_posts_with_author_names(): void
    {
        $author = User::factory()->create(['first_name' => 'Taylor', 'middle_name' => null, 'last_name' => 'Reed']);
        $viewer = User::factory()->create();
        Permission::findOrCreate('community-feed.view', 'web');
        $viewer->givePermissionTo('community-feed.view');

        $visible = CommunityPost::create(['author_id' => $author->id, 'body' => "First update\nSecond line"]);
        CommunityPost::create(['body' => 'Archived update', 'archived_at' => now()]);
        $deleted = CommunityPost::create(['body' => 'Deleted update']);
        $deleted->delete();

        $this->actingAs($viewer)->get('/community')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Community/Index')
                ->where('posts.total', 1)
                ->where('posts.data.0.id', $visible->id)
                ->where('posts.data.0.body', "First update\nSecond line")
                ->where('posts.data.0.author', 'Taylor Reed')
                ->where('posts.data.0.can_update', false)
                ->where('posts.data.0.can_delete', false));
    }

    public function test_creating_posts_requires_its_permission_and_logs_a_safe_audit_event(): void
    {
        $viewer = User::factory()->create();
        Permission::findOrCreate('community-feed.view', 'web');
        $viewer->givePermissionTo('community-feed.view');
        $this->actingAs($viewer)->post('/community', ['body' => 'Not allowed'])->assertForbidden();
        $this->assertDatabaseCount('community_posts', 0);

        Permission::findOrCreate('community-feed.create', 'web');
        $viewer->givePermissionTo('community-feed.create');
        $this->actingAs($viewer)->post('/community', ['body' => '  Team update  '])
            ->assertRedirect('/community')
            ->assertSessionHas('status', 'Post shared with your team.');

        $post = CommunityPost::sole();
        $this->assertSame($viewer->id, $post->author_id);
        $this->assertSame('Team update', $post->body);
        $activity = Activity::query()->where('event', 'community.post_created')->sole();
        $this->assertSame($viewer->id, $activity->causer_id);
        $this->assertSame($post->id, $activity->subject_id);
        $this->assertSame([], $activity->properties->toArray());
    }

    public function test_post_images_are_inspected_stored_privately_and_served_to_feed_readers(): void
    {
        Storage::fake('local');
        $author = User::factory()->create();
        $reader = User::factory()->create();
        $author->givePermissionTo(Permission::findOrCreate('community-feed.create', 'web'));
        $reader->givePermissionTo(Permission::findOrCreate('community-feed.view', 'web'));
        $image = UploadedFile::fake()->image('team.png', 20, 20, 'png');

        $this->actingAs($author)->post('/community', ['body' => 'With an image', 'media' => [$image]])
            ->assertRedirect('/community');

        $post = CommunityPost::sole();
        $media = CommunityPostMedia::sole();
        $this->assertSame($post->id, $media->community_post_id);
        $this->assertSame('team.png', $media->original_name);
        Storage::disk('local')->assertExists($media->path);
        $this->assertDatabaseHas('activity_log', ['event' => 'community.media_uploaded', 'subject_id' => $media->id]);

        $this->actingAs($reader)->get('/community')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('posts.data.0.media.0.name', 'team.png'));
        $this->actingAs($reader)->get("/community/{$post->id}/media/{$media->id}")
            ->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs(User::factory()->create())->get("/community/{$post->id}/media/{$media->id}")->assertForbidden();
    }

    public function test_post_image_upload_requires_create_permission_and_rejects_invalid_images(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('community-feed.view', 'web'));
        $image = UploadedFile::fake()->image('team.png', 20, 20, 'png');
        $this->actingAs($user)->post('/community', ['body' => 'No permission', 'media' => [$image]])->assertForbidden();
        $this->assertDatabaseCount('community_posts', 0);

        $user->givePermissionTo(Permission::findOrCreate('community-feed.create', 'web'));
        $invalid = UploadedFile::fake()->create('spoofed.png', 5, 'image/png');
        $this->actingAs($user)->post('/community', ['body' => 'Invalid image', 'media' => [$invalid]])
            ->assertSessionHasErrors('media.0');
        $this->assertDatabaseCount('community_posts', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_strict_media_inspection_rejects_active_content_before_saving(): void
    {
        Storage::fake('local');
        $author = User::factory()->create();
        $author->givePermissionTo(Permission::findOrCreate('community-feed.create', 'web'));
        $jpeg = UploadedFile::fake()->image('active.jpg', 20, 20, 'jpg')->get();
        $marker = '<script>alert(1)</script>';
        $jpegWithScript = substr($jpeg, 0, -2)."\xFF\xFE".pack('n', strlen($marker) + 2).$marker."\xFF\xD9";
        config()->set('demo.community_post_media_strict_inspection', true);

        $this->actingAs($author)->post('/community', [
            'body' => 'Active image content',
            'media' => [UploadedFile::fake()->createWithContent('active.jpg', $jpegWithScript)],
        ])->assertSessionHasErrors('media.0');

        $this->assertDatabaseCount('community_posts', 0);
        $this->assertDatabaseCount('community_post_media', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_each_community_media_upload_path_enforces_the_10_mb_file_limit(): void
    {
        Storage::fake('local');
        $author = User::factory()->create();
        $author->givePermissionTo(Permission::findOrCreate('community-feed.create', 'web'));
        $author->givePermissionTo(Permission::findOrCreate('community-feed.update', 'web'));
        $expectedError = trans('validation.max.file', ['attribute' => 'media.0', 'max' => 10240]);

        $this->actingAs($author)->post('/community', [
            'body' => 'Too large on create',
            'media' => [UploadedFile::fake()->create('too-large.png', 10241, 'image/png')],
        ])->assertSessionHasErrors(['media.0' => $expectedError]);

        $post = CommunityPost::create(['author_id' => $author->id, 'body' => 'Add media later']);
        $this->actingAs($author)->post("/community/{$post->id}/media", [
            'media' => [UploadedFile::fake()->create('too-large.png', 10241, 'image/png')],
        ])->assertSessionHasErrors(['media.0' => $expectedError]);

        $this->assertDatabaseCount('community_posts', 1);
        $this->assertDatabaseCount('community_post_media', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_post_image_removal_requires_its_author_and_update_permission_and_is_audited(): void
    {
        Storage::fake('local');
        $author = User::factory()->create();
        $otherEditor = User::factory()->create();
        $author->givePermissionTo(Permission::findOrCreate('community-feed.create', 'web'));
        $author->givePermissionTo(Permission::findOrCreate('community-feed.update', 'web'));
        $otherEditor->givePermissionTo(Permission::findOrCreate('community-feed.update', 'web'));
        $this->actingAs($author)->post('/community', [
            'body' => 'Image to remove',
            'media' => [UploadedFile::fake()->image('team.png', 20, 20, 'png')],
        ])->assertRedirect('/community');

        $post = CommunityPost::sole();
        $media = CommunityPostMedia::sole();
        $url = "/community/{$post->id}/media/{$media->id}";
        Storage::disk('local')->assertExists($media->path);

        $this->actingAs($otherEditor)->delete($url)->assertForbidden();
        $this->assertDatabaseHas('community_post_media', ['id' => $media->id]);
        Storage::disk('local')->assertExists($media->path);

        $this->actingAs($author)->delete($url)->assertRedirect('/community')
            ->assertSessionHas('status', 'Image removed from the post.');
        $this->assertDatabaseMissing('community_post_media', ['id' => $media->id]);
        Storage::disk('local')->assertMissing($media->path);
        $this->assertDatabaseHas('activity_log', ['event' => 'community.media_deleted', 'subject_id' => $media->id]);
    }

    public function test_post_author_can_add_images_later_without_exceeding_the_four_image_limit(): void
    {
        Storage::fake('local');
        $author = User::factory()->create();
        $otherEditor = User::factory()->create();
        $author->givePermissionTo(Permission::findOrCreate('community-feed.create', 'web'));
        $author->givePermissionTo(Permission::findOrCreate('community-feed.update', 'web'));
        $otherEditor->givePermissionTo(Permission::findOrCreate('community-feed.update', 'web'));
        $this->actingAs($author)->post('/community', ['body' => 'Text first'])->assertRedirect('/community');
        $post = CommunityPost::sole();

        $this->actingAs($otherEditor)->post("/community/{$post->id}/media", [
            'media' => [UploadedFile::fake()->image('blocked.png', 20, 20, 'png')],
        ])->assertForbidden();

        $this->actingAs($author)->post("/community/{$post->id}/media", [
            'media' => [
                UploadedFile::fake()->image('first.png', 20, 20, 'png'),
                UploadedFile::fake()->image('second.png', 20, 20, 'png'),
                UploadedFile::fake()->image('third.png', 20, 20, 'png'),
            ],
        ])->assertRedirect('/community')->assertSessionHas('status', 'Images added to the post.');
        $this->assertDatabaseCount('community_post_media', 3);

        $this->actingAs($author)->post("/community/{$post->id}/media", [
            'media' => [
                UploadedFile::fake()->image('fourth.png', 20, 20, 'png'),
                UploadedFile::fake()->image('fifth.png', 20, 20, 'png'),
            ],
        ])->assertSessionHasErrors('media');
        $this->assertDatabaseCount('community_post_media', 3);

        $this->actingAs($author)->post("/community/{$post->id}/media", [
            'media' => [UploadedFile::fake()->image('fourth.png', 20, 20, 'png')],
        ])->assertRedirect('/community');
        $this->assertDatabaseCount('community_post_media', 4);
        $this->assertDatabaseCount('activity_log', 5);
    }

    public function test_post_body_is_required_and_limited_to_5000_characters(): void
    {
        $author = User::factory()->create();
        Permission::findOrCreate('community-feed.create', 'web');
        $author->givePermissionTo('community-feed.create');

        $this->actingAs($author)->post('/community', ['body' => " \n "])->assertSessionHasErrors('body');
        $this->actingAs($author)->post('/community', ['body' => str_repeat('a', 5001)])->assertSessionHasErrors('body');
        $this->assertDatabaseCount('community_posts', 0);
    }

    public function test_post_updates_require_update_permission_and_ownership_and_are_audited(): void
    {
        $author = User::factory()->create();
        $editor = User::factory()->create();
        $otherPost = CommunityPost::create(['author_id' => $author->id, 'body' => 'Keep private from editor changes']);
        Permission::findOrCreate('community-feed.update', 'web');
        $editor->givePermissionTo('community-feed.update');

        $this->actingAs($editor)->put("/community/{$otherPost->id}", ['body' => 'Unauthorized change'])->assertForbidden();
        $this->assertSame('Keep private from editor changes', $otherPost->fresh()->body);
        $authorPost = CommunityPost::create(['author_id' => $author->id, 'body' => 'No update permission']);
        $this->actingAs($author)->put("/community/{$authorPost->id}", ['body' => 'Still unauthorized'])->assertForbidden();
        $this->assertSame('No update permission', $authorPost->fresh()->body);

        $ownPost = CommunityPost::create(['author_id' => $editor->id, 'body' => 'Original update']);
        $this->actingAs($editor)->put("/community/{$ownPost->id}", ['body' => '  Revised update  '])
            ->assertRedirect('/community')
            ->assertSessionHas('status', 'Post updated.');
        $this->assertSame('Revised update', $ownPost->fresh()->body);
        $activity = Activity::query()->where('event', 'community.post_updated')->sole();
        $this->assertSame($editor->id, $activity->causer_id);
        $this->assertSame($ownPost->id, $activity->subject_id);
        $this->assertSame([], $activity->properties->toArray());

        Permission::findOrCreate('community-feed.view', 'web');
        $editor->givePermissionTo('community-feed.view');
        $this->actingAs($editor)->get('/community')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('posts.data.0.id', $ownPost->id)
            ->where('posts.data.0.can_update', true)
            ->where('posts.data.0.can_delete', false));
    }

    public function test_post_deletion_requires_delete_permission_and_ownership_and_uses_soft_deletes(): void
    {
        $author = User::factory()->create();
        $moderator = User::factory()->create();
        $otherPost = CommunityPost::create(['author_id' => $author->id, 'body' => 'Do not delete this post']);
        Permission::findOrCreate('community-feed.delete', 'web');
        $moderator->givePermissionTo('community-feed.delete');

        $this->actingAs($moderator)->delete("/community/{$otherPost->id}")->assertForbidden();
        $this->assertDatabaseHas('community_posts', ['id' => $otherPost->id, 'deleted_at' => null]);

        $ownPost = CommunityPost::create(['author_id' => $author->id, 'body' => 'Own update']);
        $this->actingAs($author)->delete("/community/{$ownPost->id}")->assertForbidden();
        $this->assertDatabaseHas('community_posts', ['id' => $ownPost->id, 'deleted_at' => null]);
        $author->givePermissionTo('community-feed.delete');
        $this->actingAs($author)->delete("/community/{$ownPost->id}")
            ->assertRedirect('/community')
            ->assertSessionHas('status', 'Post moved to trash.');
        $this->assertSoftDeleted('community_posts', ['id' => $ownPost->id]);
        $activity = Activity::query()->where('event', 'community.post_trashed')->sole();
        $this->assertSame($author->id, $activity->causer_id);
        $this->assertSame($ownPost->id, $activity->subject_id);
        $this->assertSame([], $activity->properties->toArray());
    }

    public function test_archive_and_unarchive_require_permission_and_are_separate_from_trash(): void
    {
        $moderator = User::factory()->create();
        $viewer = User::factory()->create();
        Permission::findOrCreate('community-feed.view', 'web');
        Permission::findOrCreate('community-feed.archive', 'web');
        $viewer->givePermissionTo('community-feed.view');
        $post = CommunityPost::create(['author_id' => $moderator->id, 'body' => 'Keep for reference']);

        $this->actingAs($viewer)->patch("/community/{$post->id}/archive")->assertForbidden();
        $moderator->givePermissionTo('community-feed.archive');
        $this->actingAs($moderator)->patch("/community/{$post->id}/archive")
            ->assertRedirect('/community')
            ->assertSessionHas('status', 'Post archived.');
        $this->assertNotNull($post->fresh()->archived_at);
        $this->assertDatabaseHas('community_posts', ['id' => $post->id, 'deleted_at' => null]);

        $this->actingAs($viewer)->get('/community?view=archived')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('view', 'archived')
                ->where('posts.total', 1)
                ->where('posts.data.0.id', $post->id));
        $this->actingAs($viewer)->get('/community')->assertInertia(fn (AssertableInertia $page) => $page->where('posts.total', 0));

        $this->actingAs($moderator)->patch("/community/{$post->id}/unarchive")
            ->assertRedirect('/community?view=archived')
            ->assertSessionHas('status', 'Post restored from archive.');
        $this->assertNull($post->fresh()->archived_at);
        $this->assertSame(1, Activity::query()->where('event', 'community.post_archived')->count());
        $this->assertSame(1, Activity::query()->where('event', 'community.post_unarchived')->count());
    }

    public function test_trash_view_is_protected_and_restore_preserves_archive_state(): void
    {
        $moderator = User::factory()->create();
        $viewer = User::factory()->create();
        $post = CommunityPost::create(['author_id' => $moderator->id, 'body' => 'Archived then removed', 'archived_at' => now()]);
        $post->delete();
        Permission::findOrCreate('community-feed.view_trash', 'web');
        Permission::findOrCreate('community-feed.restore', 'web');

        $this->get('/community/trash')->assertRedirect('/login');
        $this->actingAs($viewer)->get('/community/trash')->assertForbidden();
        $viewer->givePermissionTo('community-feed.view_trash');
        $this->actingAs($viewer)->get('/community/trash')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Community/Trash')
                ->where('posts.total', 1)
                ->where('posts.data.0.id', $post->id)
                ->where('posts.data.0.archived_at', $post->archived_at->toIso8601String())
                ->where('can_restore_posts', false));

        $this->actingAs($viewer)->post("/community/{$post->id}/restore")->assertForbidden();
        $moderator->givePermissionTo('community-feed.restore');
        $this->actingAs($moderator)->post("/community/{$post->id}/restore")
            ->assertRedirect('/community/trash')
            ->assertSessionHas('status', 'Post restored from trash.');
        $post->refresh();
        $this->assertFalse($post->trashed());
        $this->assertNotNull($post->archived_at);
        $activity = Activity::query()->where('event', 'community.post_restored')->sole();
        $this->assertSame($moderator->id, $activity->causer_id);
        $this->assertSame($post->id, $activity->subject_id);
        $this->assertSame([], $activity->properties->toArray());
    }

    public function test_permanent_delete_requires_permission_and_a_trashed_post(): void
    {
        Storage::fake('local');
        $moderator = User::factory()->create();
        $post = CommunityPost::create(['author_id' => $moderator->id, 'body' => 'Delete only after trashing']);
        $path = "community-post-media/{$post->id}/private.png";
        $post->media()->create([
            'uploaded_by' => $moderator->id,
            'original_name' => 'private.png',
            'path' => $path,
            'mime_type' => 'image/png',
            'size' => 4,
        ]);
        Storage::disk('local')->put($path, 'test');
        $post->delete();

        $this->actingAs($moderator)->delete("/community/{$post->id}/force-delete")->assertForbidden();
        $this->assertSoftDeleted('community_posts', ['id' => $post->id]);
        Storage::disk('local')->assertExists($path);

        Permission::findOrCreate('community-feed.force-delete', 'web');
        $moderator->givePermissionTo('community-feed.force-delete');
        $active = CommunityPost::create(['author_id' => $moderator->id, 'body' => 'Keep active']);
        $this->actingAs($moderator)->delete("/community/{$active->id}/force-delete")->assertStatus(409);
        $this->assertDatabaseHas('community_posts', ['id' => $active->id]);

        $this->actingAs($moderator)->delete("/community/{$post->id}/force-delete")
            ->assertRedirect('/community/trash')
            ->assertSessionHas('status', 'Post permanently deleted.');
        $this->assertDatabaseMissing('community_posts', ['id' => $post->id]);
        $this->assertDatabaseMissing('community_post_media', ['community_post_id' => $post->id]);
        Storage::disk('local')->assertMissing($path);
        $activity = Activity::query()->where('event', 'community.post_force_deleted')->sole();
        $this->assertSame($moderator->id, $activity->causer_id);
        $this->assertSame($post->id, $activity->subject_id);
        $this->assertSame([], $activity->properties->toArray());
    }

    public function test_post_moderation_hides_and_restores_posts_with_permission_and_audit(): void
    {
        $moderator = User::factory()->create();
        $viewer = User::factory()->create();
        $post = CommunityPost::create(['body' => 'Hidden post content']);
        $post->comments()->create(['body' => 'Hidden post comment']);
        Permission::findOrCreate('community-feed.view', 'web');
        Permission::findOrCreate('community-feed.comment', 'web');
        Permission::findOrCreate('community-feed.moderate_posts', 'web');
        $moderator->givePermissionTo(['community-feed.view', 'community-feed.moderate_posts']);
        $viewer->givePermissionTo(['community-feed.view', 'community-feed.comment']);

        $this->actingAs($viewer)->patch("/community/{$post->id}/moderation", ['action' => 'hide'])->assertForbidden();
        $this->actingAs($viewer)->get('/community?view=moderated')->assertForbidden();
        $this->actingAs($moderator)->patch("/community/{$post->id}/moderation", ['action' => 'invalid'])->assertSessionHasErrors('action');
        $this->actingAs($moderator)->patch("/community/{$post->id}/moderation", ['action' => 'hide'])
            ->assertRedirect('/community?view=moderated')
            ->assertSessionHas('status', 'Post hidden from the feed.');
        $this->assertDatabaseHas('community_posts', ['id' => $post->id, 'moderated_by' => $moderator->id]);
        $hidden = Activity::query()->where('event', 'community.post_moderated')->sole();
        $this->assertSame($moderator->id, $hidden->causer_id);
        $this->assertSame($post->id, $hidden->subject_id);
        $this->assertSame([], $hidden->properties->toArray());
        $this->actingAs($viewer)->get('/community')->assertInertia(fn (AssertableInertia $page) => $page->where('posts.total', 0));
        $this->actingAs($viewer)->get("/community/{$post->id}/comments")->assertNotFound();
        $this->actingAs($viewer)->post("/community/{$post->id}/comments", ['body' => 'Not allowed'])->assertForbidden();
        $this->actingAs($moderator)->get('/community?view=moderated')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('posts.total', 1)
                ->where('posts.data.0.id', $post->id)
                ->where('posts.data.0.moderated_at', $post->fresh()->moderated_at->toIso8601String())
                ->where('posts.data.0.can_moderate', true)
                ->where('can_view_moderated', true));

        $this->actingAs($moderator)->patch("/community/{$post->id}/moderation", ['action' => 'restore'])
            ->assertRedirect('/community')
            ->assertSessionHas('status', 'Post restored to the feed.');
        $this->assertDatabaseHas('community_posts', ['id' => $post->id, 'moderated_at' => null, 'moderated_by' => null]);
        $restored = Activity::query()->where('event', 'community.post_unmoderated')->sole();
        $this->assertSame($moderator->id, $restored->causer_id);
        $this->assertSame([], $restored->properties->toArray());
    }

    public function test_comment_moderation_is_permission_gated_audited_and_hidden_from_non_moderators(): void
    {
        $moderator = User::factory()->create();
        $viewer = User::factory()->create();
        $post = CommunityPost::create(['body' => 'Post with moderation']);
        $root = $post->comments()->create(['body' => 'Root comment']);
        $reply = $root->replies()->create(['community_post_id' => $post->id, 'body' => 'Reply content']);
        $anotherPost = CommunityPost::create(['body' => 'Other post']);
        Permission::findOrCreate('community-feed.view', 'web');
        Permission::findOrCreate('community-feed.moderate_comments', 'web');
        $moderator->givePermissionTo(['community-feed.view', 'community-feed.moderate_comments']);
        $viewer->givePermissionTo('community-feed.view');

        $this->actingAs($viewer)->patch("/community/{$post->id}/comments/{$root->id}/moderation", ['action' => 'hide'])->assertForbidden();
        $this->actingAs($moderator)->patch("/community/{$anotherPost->id}/comments/{$root->id}/moderation", ['action' => 'hide'])->assertForbidden();
        $this->actingAs($moderator)->patch("/community/{$post->id}/comments/{$root->id}/moderation", ['action' => 'invalid'])->assertSessionHasErrors('action');
        $this->actingAs($moderator)->patch("/community/{$post->id}/comments/{$root->id}/moderation", ['action' => 'hide'])
            ->assertRedirect("/community/{$post->id}/comments")
            ->assertSessionHas('status', 'Comment hidden.');
        $this->assertDatabaseHas('community_comments', ['id' => $root->id, 'moderated_by' => $moderator->id]);
        $hidden = Activity::query()->where('event', 'community.comment_moderated')->sole();
        $this->assertSame($moderator->id, $hidden->causer_id);
        $this->assertSame($root->id, $hidden->subject_id);
        $this->assertSame([], $hidden->properties->toArray());

        $this->actingAs($viewer)->get("/community/{$post->id}/comments")
            ->assertInertia(fn (AssertableInertia $page) => $page->where('comments.total', 0));
        $this->actingAs($viewer)->get('/community')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('posts.data.0.comments_count', 0));
        $this->actingAs($moderator)->get("/community/{$post->id}/comments")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('comments.data.0.id', $root->id)
                ->where('comments.data.0.moderated', true)
                ->where('comments.data.0.can_moderate', true)
                ->where('comments.data.0.replies.0.id', $reply->id));

        $this->actingAs($moderator)->patch("/community/{$post->id}/comments/{$root->id}/moderation", ['action' => 'restore'])
            ->assertRedirect("/community/{$post->id}/comments")
            ->assertSessionHas('status', 'Comment restored.');
        $this->assertDatabaseHas('community_comments', ['id' => $root->id, 'moderated_at' => null, 'moderated_by' => null]);
        $restored = Activity::query()->where('event', 'community.comment_unmoderated')->sole();
        $this->assertSame($moderator->id, $restored->causer_id);
        $this->assertSame([], $restored->properties->toArray());
    }

    public function test_post_likes_are_permission_gated_unique_owned_and_audited(): void
    {
        $post = CommunityPost::create(['body' => 'A post people can like']);
        $liker = User::factory()->create();
        $anotherLiker = User::factory()->create();
        $observer = User::factory()->create();
        $restricted = User::factory()->create();
        Permission::findOrCreate('community-feed.view', 'web');
        Permission::findOrCreate('community-feed.like', 'web');
        $liker->givePermissionTo(['community-feed.view', 'community-feed.like']);
        $anotherLiker->givePermissionTo('community-feed.like');
        $observer->givePermissionTo(['community-feed.view', 'community-feed.like']);

        $this->post("/community/{$post->id}/like")->assertRedirect('/login');
        $this->actingAs($restricted)->post("/community/{$post->id}/like")->assertForbidden();
        $this->actingAs($liker)->post("/community/{$post->id}/like")->assertRedirect('/community');
        $this->actingAs($liker)->post("/community/{$post->id}/like")->assertRedirect('/community');
        $this->assertDatabaseCount('community_post_likes', 1);
        $this->assertDatabaseCount('activity_log', 1);

        $this->actingAs($anotherLiker)->post("/community/{$post->id}/like")->assertRedirect('/community');
        $this->actingAs($liker)->get('/community')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('posts.data.0.likes_count', 2)
                ->where('posts.data.0.liked_by_user', true)
                ->where('posts.data.0.can_like', true));
        $this->actingAs($observer)->delete("/community/{$post->id}/like")->assertRedirect('/community');
        $this->assertDatabaseCount('community_post_likes', 2);

        $this->actingAs($liker)->delete("/community/{$post->id}/like")->assertRedirect('/community');
        $this->assertDatabaseHas('community_post_likes', ['community_post_id' => $post->id, 'user_id' => $anotherLiker->id]);
        $this->actingAs($anotherLiker)->delete("/community/{$post->id}/like")->assertRedirect('/community');
        $this->assertDatabaseCount('community_post_likes', 0);
        $this->assertSame(2, Activity::query()->where('event', 'community.post_liked')->count());
        $this->assertSame(2, Activity::query()->where('event', 'community.post_unliked')->count());
        $this->assertSame([], Activity::query()->where('event', 'community.post_liked')->firstOrFail()->properties->toArray());

        $post->forceFill(['archived_at' => now()])->save();
        $this->actingAs($liker)->post("/community/{$post->id}/like")->assertForbidden();
        $this->actingAs($liker)->delete("/community/{$post->id}/like")->assertForbidden();
        $this->actingAs($observer)->get('/community?view=archived')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('posts.data.0.likes_count', 0)
                ->where('posts.data.0.liked_by_user', false)
                ->where('posts.data.0.can_like', false));
    }

    public function test_comments_are_paginated_and_creation_requires_permission_and_a_active_post(): void
    {
        $author = User::factory()->create();
        $commenter = User::factory()->create();
        $post = CommunityPost::create(['author_id' => $author->id, 'body' => 'Discuss this update']);
        Permission::findOrCreate('community-feed.view', 'web');
        Permission::findOrCreate('community-feed.comment', 'web');

        $this->get("/community/{$post->id}/comments")->assertRedirect('/login');
        $this->actingAs($commenter)->get("/community/{$post->id}/comments")->assertForbidden();
        $commenter->givePermissionTo('community-feed.view');
        $this->actingAs($commenter)->get("/community/{$post->id}/comments")->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Community/Comments')
                ->where('post.id', $post->id)
                ->where('comments.total', 0)
                ->where('can_comment', false));
        $this->actingAs($commenter)->post("/community/{$post->id}/comments", ['body' => 'No permission'])->assertForbidden();
        $commenter->givePermissionTo('community-feed.comment');
        $this->actingAs($commenter)->post("/community/{$post->id}/comments", ['body' => " \n "])->assertSessionHasErrors('body');
        $this->actingAs($commenter)->post("/community/{$post->id}/comments", ['body' => str_repeat('a', 2001)])->assertSessionHasErrors('body');
        $this->assertDatabaseCount('community_comments', 0);
        $this->actingAs($commenter)->post("/community/{$post->id}/comments", ['body' => '  Useful comment  '])
            ->assertRedirect("/community/{$post->id}/comments")
            ->assertSessionHas('status', 'Comment added.');

        $comment = CommunityComment::sole();
        $this->assertSame($post->id, $comment->community_post_id);
        $this->assertSame($commenter->id, $comment->author_id);
        $this->assertSame('Useful comment', $comment->body);
        $activity = Activity::query()->where('event', 'community.comment_created')->sole();
        $this->assertSame($commenter->id, $activity->causer_id);
        $this->assertSame($comment->id, $activity->subject_id);
        $this->assertSame([], $activity->properties->toArray());

        foreach (range(1, 25) as $index) {
            CommunityComment::create(['community_post_id' => $post->id, 'body' => "Comment {$index}"]);
        }
        $this->actingAs($commenter)->get("/community/{$post->id}/comments")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('comments.total', 26)
                ->where('comments.last_page', 2)
                ->where('can_comment', true));

        $post->forceFill(['archived_at' => now()])->save();
        $this->actingAs($commenter)->post("/community/{$post->id}/comments", ['body' => 'Archived discussion'])->assertForbidden();
        $this->assertDatabaseCount('community_comments', 26);
    }

    public function test_comment_updates_and_deletes_enforce_permission_ownership_and_parent(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $post = CommunityPost::create(['author_id' => $other->id, 'body' => 'Post one']);
        $anotherPost = CommunityPost::create(['author_id' => $other->id, 'body' => 'Post two']);
        $ownComment = CommunityComment::create(['community_post_id' => $post->id, 'author_id' => $owner->id, 'body' => 'Original comment']);
        $otherComment = CommunityComment::create(['community_post_id' => $post->id, 'author_id' => $other->id, 'body' => 'Other comment']);
        Permission::findOrCreate('community-feed.update_comment', 'web');
        Permission::findOrCreate('community-feed.delete_comment', 'web');
        $owner->givePermissionTo(['community-feed.update_comment', 'community-feed.delete_comment']);

        $this->actingAs($owner)->put("/community/{$post->id}/comments/{$otherComment->id}", ['body' => 'Unauthorized'])->assertForbidden();
        $this->actingAs($owner)->put("/community/{$anotherPost->id}/comments/{$ownComment->id}", ['body' => 'Wrong parent'])->assertForbidden();
        $this->actingAs($owner)->put("/community/{$post->id}/comments/{$ownComment->id}", ['body' => '  Updated comment  '])
            ->assertRedirect("/community/{$post->id}/comments")
            ->assertSessionHas('status', 'Comment updated.');
        $this->assertSame('Updated comment', $ownComment->fresh()->body);
        $updated = Activity::query()->where('event', 'community.comment_updated')->sole();
        $this->assertSame($owner->id, $updated->causer_id);
        $this->assertSame([], $updated->properties->toArray());

        $this->actingAs($owner)->delete("/community/{$post->id}/comments/{$otherComment->id}")->assertForbidden();
        $this->actingAs($owner)->delete("/community/{$anotherPost->id}/comments/{$ownComment->id}")->assertForbidden();
        $this->actingAs($owner)->delete("/community/{$post->id}/comments/{$ownComment->id}")
            ->assertRedirect("/community/{$post->id}/comments")
            ->assertSessionHas('status', 'Comment deleted.');
        $this->assertDatabaseMissing('community_comments', ['id' => $ownComment->id]);
        $deleted = Activity::query()->where('event', 'community.comment_deleted')->sole();
        $this->assertSame($owner->id, $deleted->causer_id);
        $this->assertSame($ownComment->id, $deleted->subject_id);
        $this->assertSame([], $deleted->properties->toArray());
    }

    public function test_replies_require_permission_root_comment_and_active_post_and_are_audited(): void
    {
        $postAuthor = User::factory()->create();
        $replyAuthor = User::factory()->create();
        $viewer = User::factory()->create();
        $post = CommunityPost::create(['author_id' => $postAuthor->id, 'body' => 'Post with a discussion']);
        $otherPost = CommunityPost::create(['author_id' => $postAuthor->id, 'body' => 'Another post']);
        $root = $post->comments()->create(['author_id' => $postAuthor->id, 'body' => 'Root comment']);
        $otherRoot = $otherPost->comments()->create(['body' => 'Other root']);
        Permission::findOrCreate('community-feed.view', 'web');
        Permission::findOrCreate('community-feed.reply', 'web');
        Permission::findOrCreate('community-feed.update_comment', 'web');
        Permission::findOrCreate('community-feed.delete_comment', 'web');

        $this->actingAs($replyAuthor)->post("/community/{$post->id}/comments/{$root->id}/replies", ['body' => 'No permission'])->assertForbidden();
        $replyAuthor->givePermissionTo(['community-feed.reply', 'community-feed.view', 'community-feed.update_comment', 'community-feed.delete_comment']);
        $this->actingAs($replyAuthor)->post("/community/{$post->id}/comments/{$root->id}/replies", ['body' => " \n "])->assertSessionHasErrors('body');
        $this->actingAs($replyAuthor)->post("/community/{$post->id}/comments/{$root->id}/replies", ['body' => str_repeat('a', 2001)])->assertSessionHasErrors('body');
        $this->actingAs($replyAuthor)->post("/community/{$otherPost->id}/comments/{$root->id}/replies", ['body' => 'Wrong post'])->assertForbidden();

        $this->actingAs($replyAuthor)->post("/community/{$post->id}/comments/{$root->id}/replies", ['body' => '  A useful reply  '])
            ->assertRedirect("/community/{$post->id}/comments")
            ->assertSessionHas('status', 'Reply added.');
        $reply = CommunityComment::query()->where('parent_comment_id', $root->id)->sole();
        $this->assertSame('A useful reply', $reply->body);
        $this->assertSame($replyAuthor->id, $reply->author_id);
        $activity = Activity::query()->where('event', 'community.reply_created')->sole();
        $this->assertSame($replyAuthor->id, $activity->causer_id);
        $this->assertSame($reply->id, $activity->subject_id);
        $this->assertSame([], $activity->properties->toArray());

        $this->actingAs($replyAuthor)->post("/community/{$post->id}/comments/{$reply->id}/replies", ['body' => 'Nested reply'])->assertForbidden();
        $this->actingAs($replyAuthor)->get("/community/{$post->id}/comments")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('comments.total', 1)
                ->where('comments.data.0.id', $root->id)
                ->where('comments.data.0.can_reply', true)
                ->where('comments.data.0.replies.0.id', $reply->id)
                ->where('comments.data.0.replies.0.can_update', true)
                ->where('comments.data.0.replies.0.can_delete', true));

        $viewer->givePermissionTo('community-feed.view');
        $this->actingAs($viewer)->get("/community/{$post->id}/comments")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('comments.data.0.can_reply', false)
                ->where('comments.data.0.replies.0.can_update', false)
                ->where('comments.data.0.replies.0.can_delete', false));

        $post->forceFill(['archived_at' => now()])->save();
        $this->actingAs($replyAuthor)->post("/community/{$post->id}/comments/{$root->id}/replies", ['body' => 'Archived reply'])->assertForbidden();
        $this->assertDatabaseCount('community_comments', 3);
    }

    public function test_deleting_a_parent_comment_cascades_replies(): void
    {
        $post = CommunityPost::create(['body' => 'Post']);
        $root = $post->comments()->create(['body' => 'Parent']);
        $reply = $root->replies()->create(['community_post_id' => $post->id, 'body' => 'Reply']);

        $root->delete();

        $this->assertDatabaseMissing('community_comments', ['id' => $reply->id]);
    }

    public function test_permanently_deleting_a_post_cascades_its_comments(): void
    {
        $post = CommunityPost::create(['body' => 'Remove comments with parent']);
        $comment = $post->comments()->create(['body' => 'Child comment']);
        $like = $post->likes()->create(['user_id' => User::factory()->create()->id]);

        $post->forceDelete();

        $this->assertDatabaseMissing('community_comments', ['id' => $comment->id]);
        $this->assertDatabaseMissing('community_post_likes', ['id' => $like->id]);
    }
}
