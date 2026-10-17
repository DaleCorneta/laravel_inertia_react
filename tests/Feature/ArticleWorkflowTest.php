<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ArticleWorkflowTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, array{env: string|null, server: string|null, process: string|false}> */
    private array $originalFlags = [];

    protected function setUp(): void
    {
        foreach (['DEMO_MODULES_ENABLED', 'DEMO_BLOG_ENABLED'] as $key) {
            $this->originalFlags[$key] = [
                'env' => $_ENV[$key] ?? null,
                'server' => $_SERVER[$key] ?? null,
                'process' => getenv($key),
            ];
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
    }

    public function test_article_pages_and_creation_require_their_specific_permissions(): void
    {
        $this->get('/articles')->assertRedirect('/login');

        $user = User::factory()->create();
        $this->actingAs($user)->get('/articles')->assertForbidden();
        $this->actingAs($user)->get('/articles/create')->assertForbidden();
        $this->actingAs($user)->post('/articles', ['title' => 'Draft', 'content' => $this->document('Body')])->assertForbidden();
        $this->assertDatabaseCount('articles', 0);
    }

    public function test_authorized_users_can_search_articles_and_open_the_create_form(): void
    {
        $user = User::factory()->create();
        $this->grant($user, 'articles.view');
        $this->grant($user, 'articles.create');
        Article::create(['author_id' => $user->id, 'title' => 'Safety handbook']);
        Article::create(['author_id' => $user->id, 'title' => 'Release notes']);

        $this->actingAs($user)->get('/articles?search=safety')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Articles/Index')
                ->where('filters.search', 'safety')
                ->where('articles.total', 1)
                ->where('articles.data.0.title', 'Safety handbook')
                ->where('can_create_articles', true));

        $this->actingAs($user)->get('/articles/create')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Articles/Create'));
    }

    public function test_article_csv_export_requires_the_view_permission(): void
    {
        $this->get('/articles/export?columns%5B%5D=title')->assertRedirect('/login');
        $this->get('/articles/export/xlsx?columns%5B%5D=title')->assertRedirect('/login');
        $this->get('/articles/export/pdf?columns%5B%5D=title')->assertRedirect('/login');

        $user = User::factory()->create();
        $this->actingAs($user)->get('/articles/export?columns%5B%5D=title')->assertForbidden();
        $this->get('/articles/export/xlsx?columns%5B%5D=title')->assertForbidden();
        $this->get('/articles/export/pdf?columns%5B%5D=title')->assertForbidden();
    }

    public function test_article_csv_export_uses_filters_selected_columns_and_neutralizes_formulas(): void
    {
        $author = User::factory()->create(['first_name' => '=1+1', 'middle_name' => null, 'last_name' => 'Writer']);
        $viewer = User::factory()->create();
        $this->grant($viewer, 'articles.view');
        $archived = Article::create(['author_id' => $author->id, 'title' => '=2+2 Needle', 'content' => $this->document('Archived match')]);
        $archived->forceFill(['archived_at' => now()])->save();
        Article::create(['author_id' => $author->id, 'title' => 'Needle active', 'content' => $this->document('Active match')]);

        $response = $this->actingAs($viewer)->get('/articles/export?'.http_build_query([
            'search' => 'Needle',
            'view' => 'archived',
            'columns' => ['title', 'author'],
        ]));
        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = substr($response->streamedContent(), 3);
        $rows = array_map(static fn (string $line): array => str_getcsv($line), explode("\r\n", trim($csv)));

        $this->assertSame(['Title', 'Author'], $rows[0]);
        $this->assertSame(["'=2+2 Needle", "'=1+1 Writer"], $rows[1]);
        $this->assertCount(2, $rows);

        $this->actingAs($viewer)->get('/articles/export?'.http_build_query(['columns' => ['password']]))->assertSessionHasErrors('columns.0');
    }

    public function test_article_xlsx_export_reuses_filters_and_keeps_formula_values_as_text(): void
    {
        $author = User::factory()->create(['first_name' => '=1+1', 'middle_name' => null, 'last_name' => 'Writer']);
        $viewer = User::factory()->create();
        $this->grant($viewer, 'articles.view');
        $article = Article::create(['author_id' => $author->id, 'title' => '=2+2 Needle', 'content' => $this->document('Body')]);
        Article::create(['author_id' => $author->id, 'title' => 'Needle archived', 'content' => $this->document('Archived')])->forceFill(['archived_at' => now()])->save();

        $response = $this->actingAs($viewer)->get('/articles/export/xlsx?'.http_build_query([
            'search' => 'Needle',
            'view' => 'active',
            'columns' => ['title', 'author'],
        ]));
        $response->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $path = tempnam(sys_get_temp_dir(), 'article-xlsx-test-');
        file_put_contents($path, $response->streamedContent());
        $reader = new XlsxReader;
        $zip = new \ZipArchive;
        try {
            $this->assertTrue($zip->open($path));
            $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
            $zip->close();
            $reader->open($path);
            $sheets = $reader->getSheetIterator();
            $sheets->rewind();
            $rows = array_values(iterator_to_array($sheets->current()->getRowIterator()));
        } finally {
            $reader->close();
            unlink($path);
        }

        $this->assertSame(['Title', 'Author'], $rows[0]->toArray());
        $this->assertCount(2, $rows);
        $this->assertSame('=2+2 Needle', $rows[1]->cells[0]->getValue());
        $this->assertSame('=1+1 Writer', $rows[1]->cells[1]->getValue());
        $this->assertIsString($sheetXml);
        $this->assertDoesNotMatchRegularExpression('/<f(?:\s|>)/', $sheetXml);
    }

    public function test_article_pdf_export_uses_filtered_records_and_selected_columns(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $this->grant($viewer, 'articles.view');
        Article::create(['author_id' => $author->id, 'title' => 'Needle active', 'content' => $this->document('Body')]);
        Article::create(['author_id' => $author->id, 'title' => 'Needle archived', 'content' => $this->document('Body')])->forceFill(['archived_at' => now()])->save();
        $params = ['search' => 'Needle', 'view' => 'active', 'columns' => ['title', 'author']];

        $document = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $document->shouldReceive('setPaper')->once()->with('a4', 'landscape')->andReturnSelf();
        $document->shouldReceive('download')->once()->with(\Mockery::type('string'))->andReturn(response('pdf response', 200, ['Content-Type' => 'application/pdf']));
        Pdf::shouldReceive('loadView')->once()->with('exports.articles', \Mockery::on(function (array $data): bool {
            $this->assertSame(['Title', 'Author'], $data['columns']);
            $this->assertCount(1, $data['rows']);
            $this->assertSame('Needle active', $data['rows'][0][0]);

            return true;
        }))->andReturn($document);

        $this->actingAs($viewer)->get('/articles/export/pdf?'.http_build_query($params))
            ->assertOk()->assertHeader('content-type', 'application/pdf');

        $html = view('exports.articles', [
            'columns' => ['Title'],
            'rows' => [['<script>alert(1)</script>']],
            'generatedAt' => '2026-10-17 12:00:00',
        ])->render();
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_article_pdf_export_limits_large_result_sets(): void
    {
        $viewer = User::factory()->create();
        $this->grant($viewer, 'articles.view');
        $now = now()->toDateTimeString();
        collect(range(1, 1001))->map(fn (int $id): array => [
            'title' => 'Article '.$id,
            'created_at' => $now,
            'updated_at' => $now,
        ])->chunk(300)->each(fn ($rows) => DB::table('articles')->insert($rows->all()));

        $this->actingAs($viewer)->get('/articles/export/pdf?columns%5B%5D=title')->assertStatus(422);
    }

    public function test_article_creation_trims_title_sets_actor_and_draft_and_audits_without_content(): void
    {
        $this->seed(PermissionSeeder::class);
        $author = User::factory()->create();
        $otherUser = User::factory()->create();
        $this->grant($author, 'articles.create');

        $content = $this->document('A structured draft');
        $this->actingAs($author)->post('/articles', [
            'title' => '  A new article  ',
            'content' => $content,
            'author_id' => $otherUser->id,
            'status' => 'published',
        ])->assertRedirect('/articles')->assertSessionHas('status', 'Article draft created.');

        $article = Article::query()->sole();
        $this->assertSame('A new article', $article->title);
        $this->assertSame($author->id, $article->author_id);
        $this->assertSame('draft', $article->status);
        $this->assertSame($content, $article->content);

        $activity = Activity::query()->where('event', 'article.created')->sole();
        $this->assertSame($author->id, $activity->causer_id);
        $this->assertSame($article->id, $activity->subject_id);
        $this->assertSame([], $activity->properties->toArray());
        $this->assertStringNotContainsString('A structured draft', $activity->toJson());
    }

    public function test_article_creation_accepts_supported_rich_text_and_typography(): void
    {
        $author = User::factory()->create();
        $this->grant($author, 'articles.create');
        $document = ['type' => 'doc', 'content' => [
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Overview', 'marks' => [['type' => 'bold']]]]],
            ['type' => 'paragraph', 'content' => [
                ['type' => 'text', 'text' => 'Styled text', 'marks' => [['type' => 'textStyle', 'attrs' => ['fontFamily' => 'Inter', 'fontSize' => '18px', 'lineHeight' => '1.5']]]],
                ['type' => 'text', 'text' => ' with a link', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'https://example.com', 'target' => null, 'rel' => null, 'class' => null, 'title' => null]]]],
            ]],
            ['type' => 'bulletList', 'content' => [['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'A list item']]]]]]],
        ]];

        $this->actingAs($author)->post('/articles', ['title' => 'Formatted article', 'content' => $document])
            ->assertRedirect('/articles')
            ->assertSessionHasNoErrors();

        $this->assertSame($document, Article::query()->sole()->content);
    }

    public function test_article_edit_and_update_require_the_update_permission(): void
    {
        $user = User::factory()->create();
        $article = Article::create(['author_id' => $user->id, 'title' => 'Original', 'content' => $this->document('Original body')]);

        $this->actingAs($user)->get("/articles/{$article->id}/edit")->assertForbidden();
        $this->actingAs($user)->get("/articles/{$article->id}/preview")->assertForbidden();
        $this->actingAs($user)->put("/articles/{$article->id}", [
            'title' => 'Changed', 'content' => $this->document('Changed body'),
        ])->assertForbidden();

        $this->assertSame('Original', $article->fresh()->title);
    }

    public function test_authorized_update_keeps_author_and_draft_status_and_audits_without_content(): void
    {
        $this->seed(PermissionSeeder::class);
        $editor = User::factory()->create();
        $originalAuthor = User::factory()->create();
        $article = Article::create(['author_id' => $originalAuthor->id, 'title' => 'Original', 'content' => $this->document('Old text')]);
        $this->grant($editor, 'articles.update');

        $this->actingAs($editor)->get("/articles/{$article->id}/edit")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Articles/Edit')
                ->where('article.id', $article->id)
                ->where('article.title', 'Original'));

        $content = $this->document('Updated text');
        $this->actingAs($editor)->put("/articles/{$article->id}", [
            'title' => '  Updated title  ',
            'content' => $content,
            'author_id' => $editor->id,
            'status' => 'published',
        ])->assertRedirect('/articles')->assertSessionHas('status', 'Article draft updated.');

        $article->refresh();
        $this->assertSame('Updated title', $article->title);
        $this->assertSame($content, $article->content);
        $this->assertSame($originalAuthor->id, $article->author_id);
        $this->assertSame('draft', $article->status);

        $activity = Activity::query()->where('event', 'article.updated')->sole();
        $this->assertSame($editor->id, $activity->causer_id);
        $this->assertSame($article->id, $activity->subject_id);
        $this->assertSame([], $activity->properties->toArray());
        $this->assertStringNotContainsString('Updated text', $activity->toJson());
    }

    public function test_invalid_article_update_does_not_change_saved_draft(): void
    {
        $editor = User::factory()->create();
        $this->grant($editor, 'articles.update');
        $article = Article::create(['author_id' => $editor->id, 'title' => 'Original', 'content' => $this->document('Original body')]);

        $this->actingAs($editor)->put("/articles/{$article->id}", [
            'title' => 'Changed',
            'content' => ['type' => 'doc', 'content' => [['type' => 'script']]],
        ])->assertSessionHasErrors('content');

        $this->assertSame('Original', $article->fresh()->title);
        $this->assertSame($this->document('Original body'), $article->fresh()->content);
        $this->assertDatabaseMissing('activity_log', ['event' => 'article.updated']);
    }

    public function test_article_preview_requires_permission_and_only_returns_valid_drafts(): void
    {
        $previewer = User::factory()->create();
        $article = Article::create(['author_id' => $previewer->id, 'title' => 'Preview me', 'content' => $this->document('Visible draft text')]);
        $this->grant($previewer, 'articles.preview');

        $this->actingAs($previewer)->get("/articles/{$article->id}/preview")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Articles/Preview')
                ->where('article.id', $article->id)
                ->where('article.title', 'Preview me')
                ->where('article.content.content.0.content.0.text', 'Visible draft text')
                ->where('can_update_articles', false));

        $article->update(['content' => ['type' => 'doc', 'content' => [['type' => 'script', 'content' => []]]]]);
        $this->actingAs($previewer)->get("/articles/{$article->id}/preview")->assertNotFound();

        $article->update(['content' => ['type' => 'doc', 'content' => [[
            'type' => 'paragraph',
            'content' => [['type' => 'text', 'text' => 'unsafe link', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'javascript:alert(1)']]]]],
        ]]]]);
        $this->actingAs($previewer)->get("/articles/{$article->id}/preview")->assertNotFound();

        $article->update(['content' => $this->document('Hidden published text'), 'status' => 'published']);
        $this->actingAs($previewer)->get("/articles/{$article->id}/preview")->assertNotFound();
    }

    public function test_publishing_and_unpublishing_are_separately_authorized_audited_and_public(): void
    {
        $author = User::factory()->create();
        $article = Article::create([
            'author_id' => $author->id,
            'title' => 'Public announcement',
            'content' => $this->document('Published article body'),
        ]);

        $this->actingAs($author)->post("/articles/{$article->id}/publish")->assertForbidden();
        $this->grant($author, 'articles.publish');
        $this->actingAs($author)->post("/articles/{$article->id}/publish")
            ->assertRedirect('/articles')
            ->assertSessionHas('status', 'Article published.');

        $article->refresh();
        $this->assertSame('published', $article->status);
        $this->assertNotNull($article->published_at);
        $this->actingAs($author)->post("/articles/{$article->id}/publish")->assertStatus(409);
        $this->actingAs($author)->post("/articles/{$article->id}/unpublish")->assertForbidden();

        $activity = Activity::query()->where('event', 'article.published')->sole();
        $this->assertSame($author->id, $activity->causer_id);
        $this->assertSame($article->id, $activity->subject_id);
        $this->assertSame([], $activity->properties->toArray());
        $this->assertStringNotContainsString('Published article body', $activity->toJson());

        $this->get('/blog')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Blog/Index')
            ->where('articles.data.0.title', 'Public announcement'));
        $this->get("/blog/{$article->id}")->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Blog/Show')
            ->where('article.content.content.0.content.0.text', 'Published article body'));

        $this->grant($author, 'articles.unpublish');
        $this->actingAs($author)->post("/articles/{$article->id}/unpublish")
            ->assertRedirect('/articles')
            ->assertSessionHas('status', 'Article returned to drafts.');
        $this->assertSame('draft', $article->fresh()->status);
        $this->assertNull($article->fresh()->published_at);
        $this->assertDatabaseHas('activity_log', ['event' => 'article.unpublished', 'subject_id' => $article->id]);
        $this->actingAs($author)->post("/articles/{$article->id}/unpublish")->assertStatus(409);
        $this->get("/blog/{$article->id}")->assertNotFound();

        $article->forceFill(['status' => 'published', 'published_at' => now(), 'archived_at' => now()])->save();
        $this->get("/blog/{$article->id}")->assertNotFound();
    }

    public function test_invalid_stored_content_cannot_be_published(): void
    {
        $editor = User::factory()->create();
        $this->grant($editor, 'articles.publish');
        $article = Article::create(['title' => 'Bad draft', 'content' => ['type' => 'doc', 'content' => [['type' => 'script']]]]);

        $this->actingAs($editor)->post("/articles/{$article->id}/publish")->assertStatus(422);
        $this->assertSame('draft', $article->fresh()->status);
        $this->assertNull($article->fresh()->published_at);
        $this->assertDatabaseMissing('activity_log', ['event' => 'article.published']);
    }

    public function test_article_archive_trash_and_restore_are_separately_authorized_and_audited(): void
    {
        $manager = User::factory()->create();
        $this->grant($manager, 'articles.view');
        $article = Article::create(['author_id' => $manager->id, 'title' => 'Recoverable article', 'content' => $this->document('Body')]);
        $article->forceFill(['status' => 'published', 'published_at' => now()])->save();

        $this->actingAs($manager)->patch("/articles/{$article->id}/archive")->assertForbidden();
        $this->actingAs($manager)->delete("/articles/{$article->id}")->assertForbidden();
        $this->grant($manager, 'articles.archive');
        $this->actingAs($manager)->patch("/articles/{$article->id}/archive")
            ->assertRedirect('/articles')
            ->assertSessionHas('status', 'Article archived.');

        $article->refresh();
        $this->assertNotNull($article->archived_at);
        $this->get("/blog/{$article->id}")->assertNotFound();
        $this->actingAs($manager)->get('/articles?view=archived')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Articles/Index')
                ->where('filters.view', 'archived')
                ->where('articles.data.0.title', 'Recoverable article'));
        $this->actingAs($manager)->get('/articles')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('articles.total', 0));

        $this->actingAs($manager)->patch("/articles/{$article->id}/unarchive")
            ->assertRedirect('/articles')
            ->assertSessionHas('status', 'Article restored from archive.');
        $this->assertNull($article->fresh()->archived_at);
        $this->get("/blog/{$article->id}")->assertOk();

        $archiveEvent = Activity::query()->where('event', 'article.archived')->sole();
        $this->assertSame($manager->id, $archiveEvent->causer_id);
        $this->assertSame($article->id, $archiveEvent->subject_id);
        $this->assertSame([], $archiveEvent->properties->toArray());
        $unarchiveEvent = Activity::query()->where('event', 'article.unarchived')->sole();
        $this->assertSame($manager->id, $unarchiveEvent->causer_id);
        $this->assertSame($article->id, $unarchiveEvent->subject_id);

        $trashedArticle = Article::create(['author_id' => $manager->id, 'title' => 'Archived published article', 'content' => $this->document('Body')]);
        $trashedArticle->forceFill(['status' => 'published', 'published_at' => now(), 'archived_at' => now()])->save();
        $this->actingAs($manager)->delete("/articles/{$trashedArticle->id}")->assertForbidden();
        $this->grant($manager, 'articles.delete');
        $this->actingAs($manager)->delete("/articles/{$trashedArticle->id}")
            ->assertRedirect('/articles')
            ->assertSessionHas('status', 'Article moved to trash.');
        $this->assertSoftDeleted('articles', ['id' => $trashedArticle->id]);
        $this->get("/blog/{$trashedArticle->id}")->assertNotFound();

        $this->actingAs($manager)->get('/articles/trash')->assertForbidden();
        $this->grant($manager, 'articles.view_trash');
        $this->actingAs($manager)->get('/articles/trash')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Articles/Trash')
                ->where('articles.data.0.title', 'Archived published article'));
        $this->actingAs($manager)->post("/articles/{$trashedArticle->id}/restore")->assertForbidden();

        $this->grant($manager, 'articles.restore');
        $this->actingAs($manager)->post("/articles/{$trashedArticle->id}/restore")
            ->assertRedirect('/articles/trash')
            ->assertSessionHas('status', 'Article restored from trash.');
        $trashedArticle->refresh();
        $this->assertFalse($trashedArticle->trashed());
        $this->assertNotNull($trashedArticle->archived_at);
        $this->get("/blog/{$trashedArticle->id}")->assertNotFound();

        foreach (['article.trashed', 'article.restored'] as $event) {
            $activity = Activity::query()->where('event', $event)->sole();
            $this->assertSame($manager->id, $activity->causer_id);
            $this->assertSame($trashedArticle->id, $activity->subject_id);
            $this->assertSame([], $activity->properties->toArray());
        }
    }

    public function test_permanent_delete_requires_permission_and_only_deletes_trashed_articles(): void
    {
        Storage::fake('local');
        $manager = User::factory()->create();
        $this->grant($manager, 'articles.view_trash');
        $active = Article::create(['title' => 'Keep this article', 'content' => $this->document('Keep this content')]);
        $trashed = Article::create(['title' => 'Remove this article', 'content' => $this->document('Private article body')]);
        $trashed->forceFill(['cover_image_path' => 'article-covers/1/cover.png'])->save();
        Storage::disk('local')->put($trashed->cover_image_path, 'cover bytes');
        $inlinePath = "article-inline-media/{$trashed->id}/inline.png";
        Storage::disk('local')->put($inlinePath, 'inline image bytes');
        $inlineMedia = $trashed->inlineMedia()->create(['path' => $inlinePath, 'mime_type' => 'image/png']);
        $trashed->delete();

        $this->actingAs($manager)->delete("/articles/{$trashed->id}/force-delete")->assertForbidden();
        Storage::disk('local')->assertExists($inlinePath);
        $this->assertDatabaseHas('article_inline_media', ['id' => $inlineMedia->id]);
        $this->grant($manager, 'articles.force-delete');
        $this->actingAs($manager)->get('/articles/trash')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('can_force_delete_articles', true));
        $this->actingAs($manager)->delete("/articles/{$active->id}/force-delete")->assertStatus(409);
        $this->assertDatabaseHas('articles', ['id' => $active->id]);
        $this->assertSoftDeleted('articles', ['id' => $trashed->id]);

        $this->actingAs($manager)->delete("/articles/{$trashed->id}/force-delete")
            ->assertRedirect('/articles/trash')
            ->assertSessionHas('status', 'Article permanently deleted.');

        $this->assertDatabaseMissing('articles', ['id' => $trashed->id]);
        Storage::disk('local')->assertMissing($trashed->cover_image_path);
        Storage::disk('local')->assertMissing($inlinePath);
        $this->assertDatabaseMissing('article_inline_media', ['id' => $inlineMedia->id]);
        $activity = Activity::query()->where('event', 'article.force_deleted')->sole();
        $this->assertSame($manager->id, $activity->causer_id);
        $this->assertSame($trashed->id, $activity->subject_id);
        $this->assertSame([], $activity->properties->toArray());
        $this->assertStringNotContainsString('Private article body', $activity->toJson());
    }

    public function test_article_history_is_separately_authorized_scoped_and_excludes_content(): void
    {
        $viewer = User::factory()->create();
        $article = Article::create(['title' => 'History target', 'content' => $this->document('Private article body')]);
        $otherArticle = Article::create(['title' => 'Another article', 'content' => $this->document('Other private body')]);
        activity('audit')->causedBy($viewer)->performedOn($article)->event('article.updated')
            ->withProperties(['content' => 'PRIVATE ARTICLE BODY'])->log('article.updated');
        activity('audit')->causedBy($viewer)->performedOn($otherArticle)->event('article.created')->log('article.created');

        $this->actingAs($viewer)->get("/articles/{$article->id}/history")->assertForbidden();
        $this->grant($viewer, 'articles.view_history');
        $this->actingAs($viewer)->get("/articles/{$article->id}/history")
            ->assertOk()
            ->assertDontSee('PRIVATE ARTICLE BODY')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Articles/History')
                ->where('article.title', 'History target')
                ->where('activities.total', 1)
                ->where('activities.data.0.action', 'Article Updated')
                ->where('activities.data.0.actor', $viewer->display_name)
                ->missing('activities.data.0.properties'));
    }

    public function test_cover_images_are_private_until_published_and_removed_on_replacement_or_removal(): void
    {
        Storage::fake('local');
        $author = User::factory()->create();
        $this->grant($author, 'articles.create');
        $this->grant($author, 'articles.update');
        $this->grant($author, 'articles.publish');
        $this->grant($author, 'articles.archive');

        $this->actingAs($author)->post('/articles', [
            'title' => 'Illustrated article',
            'content' => $this->document('Article body'),
            'cover_image' => UploadedFile::fake()->image('cover.png', 640, 360),
        ])->assertRedirect('/articles');
        $article = Article::query()->sole();
        $firstCoverPath = $article->cover_image_path;
        $this->assertNotNull($firstCoverPath);
        Storage::disk('local')->assertExists($firstCoverPath);
        $this->get("/articles/{$article->id}/cover")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get("/blog/{$article->id}/cover")->assertNotFound();

        $this->post("/articles/{$article->id}", [
            '_method' => 'put',
            'title' => $article->title,
            'content' => $article->content,
            'cover_image' => UploadedFile::fake()->image('replacement.jpg', 640, 360),
        ])->assertRedirect('/articles');
        $article->refresh();
        Storage::disk('local')->assertMissing($firstCoverPath);
        Storage::disk('local')->assertExists($article->cover_image_path);

        $this->post("/articles/{$article->id}/publish")->assertRedirect('/articles');
        $this->get("/blog/{$article->id}/cover")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->get('/blog')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('articles.data.0.cover_image_url', "/blog/{$article->id}/cover"));

        $publishedCoverPath = $article->cover_image_path;
        $this->post("/articles/{$article->id}", [
            '_method' => 'put',
            'title' => $article->title,
            'content' => $article->content,
            'remove_cover_image' => true,
        ])->assertRedirect('/articles');
        $article->refresh();
        $this->assertNull($article->cover_image_path);
        Storage::disk('local')->assertMissing($publishedCoverPath);
        $this->assertDatabaseHas('activity_log', ['event' => 'article.cover_image_uploaded', 'subject_id' => $article->id]);
        $this->assertDatabaseHas('activity_log', ['event' => 'article.cover_image_removed', 'subject_id' => $article->id]);

        $this->patch("/articles/{$article->id}/archive")->assertRedirect('/articles');
        $this->get("/blog/{$article->id}/cover")->assertNotFound();
    }

    public function test_cover_image_size_and_dimension_limits_are_enforced_on_create_and_update(): void
    {
        Storage::fake('local');
        $author = User::factory()->create();
        $this->grant($author, 'articles.create');
        $this->grant($author, 'articles.update');
        $png = UploadedFile::fake()->image('large.png', 8, 8)->get();

        $this->actingAs($author)->post('/articles', [
            'title' => 'Oversized cover',
            'content' => $this->document('Body'),
            'cover_image' => UploadedFile::fake()->createWithContent('large.png', str_pad($png, 4 * 1024 * 1024 + 1, 'x')),
        ])->assertSessionHasErrors([
            'cover_image' => trans('validation.max.file', ['attribute' => 'cover image', 'max' => 4096]),
        ]);

        $this->actingAs($author)->post('/articles', [
            'title' => 'Wide cover',
            'content' => $this->document('Body'),
            'cover_image' => UploadedFile::fake()->image('wide.png', 6001, 1),
        ])->assertSessionHasErrors('cover_image');
        $this->assertDatabaseCount('articles', 0);

        $article = Article::create([
            'author_id' => $author->id,
            'title' => 'Tall cover',
            'content' => $this->document('Body'),
        ]);
        $this->actingAs($author)->post("/articles/{$article->id}", [
            '_method' => 'put',
            'title' => $article->title,
            'content' => $article->content,
            'cover_image' => UploadedFile::fake()->image('tall.png', 1, 6001),
        ])->assertSessionHasErrors('cover_image');

        $this->assertNull($article->fresh()->cover_image_path);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_cover_image_rejects_svg_and_private_display_requires_update_permission(): void
    {
        Storage::fake('local');
        $author = User::factory()->create();
        $this->grant($author, 'articles.create');
        $this->actingAs($author)->post('/articles', [
            'title' => 'Unsafe cover',
            'content' => $this->document('Body'),
            'cover_image' => UploadedFile::fake()->create('cover.svg', 1, 'image/svg+xml'),
        ])->assertSessionHasErrors('cover_image');
        $this->assertDatabaseCount('articles', 0);

        $jpeg = UploadedFile::fake()->image('cover.jpg', 32, 32)->get();
        $marker = '<script>alert(1)</script>';
        $jpegWithScript = substr($jpeg, 0, -2)."\xFF\xFE".pack('n', strlen($marker) + 2).$marker."\xFF\xD9";
        config()->set('demo.blog_cover_images_strict_inspection', true);
        $this->actingAs($author)->from('/articles/create')->post('/articles', [
            'title' => 'Polyglot cover',
            'content' => $this->document('Body'),
            'cover_image' => UploadedFile::fake()->createWithContent('cover.jpg', $jpegWithScript),
        ])->assertSessionHasErrors('cover_image');
        $this->assertDatabaseCount('articles', 0);

        $article = Article::create(['title' => 'Private image', 'content' => $this->document('Body')]);
        $article->forceFill(['cover_image_path' => 'article-covers/1/private.txt'])->save();
        Storage::disk('local')->put($article->cover_image_path, 'not an image');
        $this->get("/articles/{$article->id}/cover")->assertForbidden();
        $this->grant($author, 'articles.update');
        $this->actingAs($author)->get("/articles/{$article->id}/cover")->assertNotFound();
    }

    public function test_inline_images_are_article_scoped_private_until_publication_and_reject_unowned_references(): void
    {
        Storage::fake('local');
        $editor = User::factory()->create();
        $unauthorized = User::factory()->create();
        $this->grant($editor, 'articles.create');
        $this->grant($editor, 'articles.update');
        $this->grant($editor, 'articles.publish');
        $article = Article::create(['title' => 'Inline image', 'content' => $this->document('Body')]);
        $other = Article::create(['title' => 'Other article', 'content' => $this->document('Body')]);

        $this->actingAs($unauthorized)->post("/articles/{$article->id}/inline-media", [
            'image' => UploadedFile::fake()->image('blocked.png', 640, 360),
        ])->assertForbidden();

        $this->actingAs($editor)->post("/articles/{$article->id}/inline-media", [
            'image' => UploadedFile::fake()->image('inline.png', 640, 360),
            'alt' => 'A test illustration',
        ])->assertOk()->assertJsonStructure(['mediaId', 'alt'])->assertJsonPath('alt', 'A test illustration');
        $media = $article->inlineMedia()->sole();
        Storage::disk('local')->assertExists($media->path);
        $this->get("/articles/{$article->id}/inline-media/{$media->id}")
            ->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('Cache-Control', 'no-store, private');
        $this->get("/articles/{$other->id}/inline-media/{$media->id}")->assertNotFound();
        $this->get("/blog/{$article->id}/inline-media/{$media->id}")->assertNotFound();

        $content = ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => 'Illustration: '],
            ['type' => 'articleImage', 'attrs' => ['mediaId' => $media->id, 'alt' => 'A test illustration']],
        ]]]];
        $this->put("/articles/{$article->id}", ['title' => $article->title, 'content' => $content])->assertRedirect('/articles');
        $this->get("/blog/{$article->id}/inline-media/{$media->id}")->assertNotFound();
        $this->actingAs($editor)->post("/articles/{$article->id}/publish")->assertRedirect('/articles');
        $this->get("/blog/{$article->id}/inline-media/{$media->id}")
            ->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get("/blog/{$article->id}")->assertInertia(fn (AssertableInertia $page) => $page
            ->where('article.content.content.0.content.1.type', 'articleImage'));

        $this->put("/articles/{$article->id}", ['title' => $article->title, 'content' => ['type' => 'doc', 'content' => [[
            'type' => 'paragraph', 'content' => [['type' => 'articleImage', 'attrs' => ['mediaId' => $media->id + 1000, 'alt' => 'foreign']]],
        ]]]])->assertSessionHasErrors('content');

        $this->put("/articles/{$article->id}", ['title' => $article->title, 'content' => $this->document('Image removed')])->assertRedirect('/articles');
        $this->assertDatabaseMissing('article_inline_media', ['id' => $media->id]);
        Storage::disk('local')->assertMissing($media->path);
        $this->get("/blog/{$article->id}/inline-media/{$media->id}")->assertNotFound();
    }

    public function test_inline_image_size_limit_and_optional_strict_inspection_are_enforced(): void
    {
        Storage::fake('local');
        $editor = User::factory()->create();
        $this->grant($editor, 'articles.update');
        $article = Article::create(['title' => 'Image validation', 'content' => $this->document('Body')]);
        $png = UploadedFile::fake()->image('large.png', 8, 8)->get();
        $oversizedPng = str_pad($png, 4 * 1024 * 1024 + 1, 'x');

        $this->actingAs($editor)->post("/articles/{$article->id}/inline-media", [
            'image' => UploadedFile::fake()->createWithContent('large.png', $oversizedPng),
        ])->assertSessionHasErrors([
            'image' => trans('validation.max.file', ['attribute' => 'image', 'max' => 4096]),
        ]);

        $jpeg = UploadedFile::fake()->image('active.jpg', 20, 20, 'jpg')->get();
        $marker = '<script>alert(1)</script>';
        $jpegWithScript = substr($jpeg, 0, -2)."\xFF\xFE".pack('n', strlen($marker) + 2).$marker."\xFF\xD9";
        config()->set('demo.blog_inline_images_strict_inspection', true);

        $this->actingAs($editor)->post("/articles/{$article->id}/inline-media", [
            'image' => UploadedFile::fake()->createWithContent('active.jpg', $jpegWithScript),
        ])->assertSessionHasErrors('image');

        $this->assertDatabaseCount('article_inline_media', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_invalid_or_active_content_is_rejected_without_creating_an_article(): void
    {
        $user = User::factory()->create();
        $this->grant($user, 'articles.create');

        $this->actingAs($user)->post('/articles', [
            'title' => 'Unsupported node',
            'content' => ['type' => 'doc', 'content' => [['type' => 'script', 'content' => []]]],
        ])->assertSessionHasErrors('content');

        $this->actingAs($user)->post('/articles', [
            'title' => 'Unsafe link',
            'content' => ['type' => 'doc', 'content' => [[
                'type' => 'paragraph',
                'content' => [[
                    'type' => 'text',
                    'text' => 'click me',
                    'marks' => [['type' => 'link', 'attrs' => ['href' => 'javascript:alert(1)']]],
                ]],
            ]]],
        ])->assertSessionHasErrors('content');

        $this->actingAs($user)->post('/articles', [
            'title' => 'Unsafe text style',
            'content' => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [[
                'type' => 'text',
                'text' => 'styled',
                'marks' => [['type' => 'textStyle', 'attrs' => ['fontFamily' => 'Arial; background:url(javascript:alert(1))']]],
            ]]]]],
        ])->assertSessionHasErrors('content');

        $this->actingAs($user)->post('/articles', [
            'title' => 'Invalid line spacing',
            'content' => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [[
                'type' => 'text',
                'text' => 'styled',
                'marks' => [['type' => 'textStyle', 'attrs' => ['lineHeight' => 'expression(alert(1))']]],
            ]]]]],
        ])->assertSessionHasErrors('content');

        $this->actingAs($user)->post('/articles', [
            'title' => 'Unsafe new tab link',
            'content' => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [[
                'type' => 'text',
                'text' => 'link',
                'marks' => [['type' => 'link', 'attrs' => ['href' => 'https://example.com', 'target' => '_blank', 'rel' => null]]],
            ]]]]],
        ])->assertSessionHasErrors('content');

        $this->assertDatabaseCount('articles', 0);
    }

    /** @return array{type: string, content: array<int, array<string, mixed>>} */
    private function document(string $text): array
    {
        return ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]]]];
    }

    private function grant(User $user, string $permission): void
    {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
}
