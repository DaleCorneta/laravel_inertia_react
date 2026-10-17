<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreArticleRequest;
use App\Http\Requests\UpdateArticleRequest;
use App\Models\Article;
use App\Models\User;
use App\Rules\ArticleDocument;
use App\Services\ArticleCoverImageService;
use App\Services\ArticleInlineImageService;
use App\Services\AuditLogger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ArticleController extends Controller
{
    private const PDF_ROW_LIMIT = 1000;

    private const EXPORT_COLUMNS = [
        'title' => 'Title',
        'status' => 'Status',
        'author' => 'Author',
        'updated_at' => 'Last updated',
    ];

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'view' => ['nullable', 'in:active,archived'],
        ]);
        $search = trim($filters['search'] ?? '');
        $view = $filters['view'] ?? 'active';

        $articles = $this->articlesQuery($search, $view)
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Article $article): array => [
                'id' => $article->id,
                'title' => $article->title,
                'status' => $article->status,
                'author' => $article->author?->display_name ?? 'Deleted account',
                'updated_at' => $article->updated_at?->toIso8601String(),
                'archived_at' => $article->archived_at?->toIso8601String(),
            ]);

        return Inertia::render('Articles/Index', [
            'articles' => $articles,
            'filters' => ['search' => $search, 'view' => $view],
            'can_create_articles' => $request->user()->can('articles.create'),
            'can_update_articles' => $request->user()->can('articles.update'),
            'can_preview_articles' => $request->user()->can('articles.preview'),
            'can_publish_articles' => $request->user()->can('articles.publish'),
            'can_unpublish_articles' => $request->user()->can('articles.unpublish'),
            'can_archive_articles' => $request->user()->can('articles.archive'),
            'can_delete_articles' => $request->user()->can('articles.delete'),
            'can_view_article_trash' => $request->user()->can('articles.view_trash'),
            'can_view_article_history' => $request->user()->can('articles.view_history'),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->validateExportFilters($request);
        $columns = $filters['columns'];
        $articles = $this->articlesQuery(trim($filters['search'] ?? ''), $filters['view'] ?? 'active');

        return response()->streamDownload(function () use ($articles, $columns): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, array_map(fn (string $column): string => self::EXPORT_COLUMNS[$column], $columns), ',', '"', '', "\r\n");

            foreach ($articles->lazy(500) as $article) {
                fputcsv($output, array_map($this->safeCsvCell(...), $this->exportValues($article, $columns)), ',', '"', '', "\r\n");
            }

            fclose($output);
        }, 'articles-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function exportXlsx(Request $request)
    {
        $filters = $this->validateExportFilters($request);
        $columns = $filters['columns'];
        $articles = $this->articlesQuery(trim($filters['search'] ?? ''), $filters['view'] ?? 'active');
        $path = tempnam(sys_get_temp_dir(), 'articles-xlsx-');
        abort_if($path === false, 500, 'Unable to prepare the export.');
        $writer = new XlsxWriter;

        try {
            $writer->openToFile($path);
            $writer->addRow(new Row(array_map(fn (string $column): StringCell => new StringCell(self::EXPORT_COLUMNS[$column]), $columns)));
            foreach ($articles->lazy(500) as $article) {
                $writer->addRow(new Row(array_map(static fn (string $value): StringCell => new StringCell($value), $this->exportValues($article, $columns))));
            }
            $writer->close();
        } catch (Throwable $exception) {
            try {
                $writer->close();
            } catch (Throwable) {
            }
            if (is_file($path)) {
                unlink($path);
            }
            throw $exception;
        }

        return response()->download($path, 'articles-'.now()->format('Ymd-His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function exportPdf(Request $request)
    {
        $filters = $this->validateExportFilters($request);
        $columns = $filters['columns'];
        $articles = $this->articlesQuery(trim($filters['search'] ?? ''), $filters['view'] ?? 'active');
        abort_if((clone $articles)->count() > self::PDF_ROW_LIMIT, 422, 'PDF exports are limited to 1,000 articles. Narrow your search and try again.');
        $rows = $articles->get()->map(fn (Article $article): array => $this->exportValues($article, $columns))->all();

        return Pdf::loadView('exports.articles', [
            'columns' => array_map(fn (string $column): string => self::EXPORT_COLUMNS[$column], $columns),
            'rows' => $rows,
            'generatedAt' => now()->format('Y-m-d H:i:s'),
        ])->setPaper('a4', 'landscape')->download('articles-'.now()->format('Ymd-His').'.pdf');
    }

    /** @return array{search?: string, view?: string, columns: list<string>} */
    private function validateExportFilters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'view' => ['nullable', 'in:active,archived'],
            'columns' => ['required', 'array', 'min:1', 'max:4'],
            'columns.*' => ['required', 'string', 'distinct:strict', 'in:'.implode(',', array_keys(self::EXPORT_COLUMNS))],
        ]);
    }

    /** @param list<string> $columns @return list<string> */
    private function exportValues(Article $article, array $columns): array
    {
        return array_map(fn (string $column): string => match ($column) {
            'title' => $article->title,
            'status' => $article->status,
            'author' => $article->author?->display_name ?? 'Deleted account',
            'updated_at' => $article->updated_at?->toDateTimeString() ?? '',
        }, $columns);
    }

    private function articlesQuery(string $search, string $view): Builder
    {
        return Article::query()
            ->with('author')
            ->when($view === 'archived', fn ($query) => $query->whereNotNull('archived_at'), fn ($query) => $query->whereNull('archived_at'))
            ->when($search !== '', fn ($query) => $query->where('title', 'like', '%'.$search.'%'))
            ->orderByDesc('updated_at')
            ->orderByDesc('id');
    }

    private function safeCsvCell(string $value): string
    {
        return preg_match('/^[\x00-\x20]*[=+@\-]/u', $value) === 1 ? "'".$value : $value;
    }

    public function history(Article $article): Response
    {
        $activities = Activity::query()
            ->inLog('audit')
            ->with('causer')
            ->where('subject_type', $article->getMorphClass())
            ->where('subject_id', $article->getKey())
            ->where('event', 'like', 'article.%')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Activity $activity): array => [
                'id' => $activity->id,
                'action' => Str::headline(str_replace('.', ' ', $activity->event ?? 'activity')),
                'actor' => $activity->causer instanceof User ? $activity->causer->display_name : 'System',
                'occurred_at' => $activity->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Articles/History', [
            'article' => ['id' => $article->id, 'title' => $article->title],
            'activities' => $activities,
        ]);
    }

    public function trash(Request $request): Response
    {
        $articles = Article::onlyTrashed()
            ->with('author')
            ->orderByDesc('deleted_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->through(fn (Article $article): array => [
                'id' => $article->id,
                'title' => $article->title,
                'status' => $article->status,
                'author' => $article->author?->display_name ?? 'Deleted account',
                'deleted_at' => $article->deleted_at?->toIso8601String(),
                'archived_at' => $article->archived_at?->toIso8601String(),
            ]);

        return Inertia::render('Articles/Trash', [
            'articles' => $articles,
            'can_restore_articles' => $request->user()->can('articles.restore'),
            'can_force_delete_articles' => $request->user()->can('articles.force-delete'),
        ]);
    }

    public function archive(Article $article, AuditLogger $auditLogger)
    {
        DB::transaction(function () use ($article, $auditLogger): void {
            $article = Article::query()->lockForUpdate()->findOrFail($article->id);
            abort_unless($article->archived_at === null, 409);

            $article->forceFill(['archived_at' => now()])->save();
            $auditLogger->log('article.archived', $article);
        });

        return to_route('articles.index')->with('status', 'Article archived.');
    }

    public function unarchive(Article $article, AuditLogger $auditLogger)
    {
        DB::transaction(function () use ($article, $auditLogger): void {
            $article = Article::query()->lockForUpdate()->findOrFail($article->id);
            abort_unless($article->archived_at !== null, 409);

            $article->forceFill(['archived_at' => null])->save();
            $auditLogger->log('article.unarchived', $article);
        });

        return to_route('articles.index')->with('status', 'Article restored from archive.');
    }

    public function destroy(Article $article, AuditLogger $auditLogger)
    {
        DB::transaction(function () use ($article, $auditLogger): void {
            $article->delete();
            $auditLogger->log('article.trashed', $article);
        });

        return to_route('articles.index')->with('status', 'Article moved to trash.');
    }

    public function restore(int $article, AuditLogger $auditLogger)
    {
        DB::transaction(function () use ($article, $auditLogger): void {
            $article = Article::withTrashed()->lockForUpdate()->findOrFail($article);
            abort_unless($article->trashed(), 409);

            $article->restore();
            $auditLogger->log('article.restored', $article);
        });

        return to_route('articles.trash')->with('status', 'Article restored from trash.');
    }

    public function forceDelete(int $article, AuditLogger $auditLogger, ArticleCoverImageService $coverImages, ArticleInlineImageService $inlineImages)
    {
        $coverPath = null;
        $inlinePaths = [];
        DB::transaction(function () use ($article, $auditLogger, &$coverPath, &$inlinePaths): void {
            $article = Article::withTrashed()->lockForUpdate()->findOrFail($article);
            abort_unless($article->trashed(), 409);

            $coverPath = $article->cover_image_path;
            $inlinePaths = $article->inlineMedia()->pluck('path')->all();
            $auditLogger->log('article.force_deleted', $article);
            $article->forceDelete();
        });

        $coverImages->delete($coverPath);
        foreach ($inlinePaths as $path) {
            $inlineImages->delete($path);
        }

        return to_route('articles.trash')->with('status', 'Article permanently deleted.');
    }

    public function create(): Response
    {
        return Inertia::render('Articles/Create');
    }

    public function store(StoreArticleRequest $request, AuditLogger $auditLogger, ArticleCoverImageService $coverImages)
    {
        $data = $request->validated();
        $image = $data['cover_image'] ?? null;
        unset($data['cover_image']);
        $coverPath = null;
        if ($image !== null) {
            $coverImages->inspect($image);
        }

        try {
            DB::transaction(function () use ($request, $data, $image, $auditLogger, $coverImages, &$coverPath): void {
                $article = Article::create([...$data, 'author_id' => $request->user()->id]);
                $auditLogger->log('article.created', $article);
                if ($image !== null) {
                    $coverPath = $coverImages->store($article, $image);
                    $article->forceFill(['cover_image_path' => $coverPath])->save();
                    $auditLogger->log('article.cover_image_uploaded', $article);
                }
            });
        } catch (Throwable $exception) {
            $coverImages->delete($coverPath);
            throw $exception;
        }

        return to_route('articles.index')->with('status', 'Article draft created.');
    }

    public function edit(Article $article): Response
    {
        return Inertia::render('Articles/Edit', [
            'article' => [
                'id' => $article->id,
                'title' => $article->title,
                'content' => $article->content,
                'cover_image_url' => $article->cover_image_path ? "/articles/{$article->id}/cover" : null,
            ],
        ]);
    }

    public function storeInlineImage(Request $request, Article $article, ArticleInlineImageService $images, AuditLogger $auditLogger)
    {
        $data = $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:4096', 'dimensions:min_width=1,min_height=1,max_width=4000,max_height=4000'],
            'alt' => ['nullable', 'string', 'max:255'],
        ]);
        abort_unless($article->status === 'draft' && $article->archived_at === null, 409);

        $media = $images->store($article, $data['image']);
        $auditLogger->log('article.inline_image_uploaded', $article);

        return response()->json(['mediaId' => $media->id, 'alt' => $data['alt'] ?? '']);
    }

    public function privateInlineImage(Request $request, Article $article, int $media)
    {
        abort_unless($request->user()->can('articles.update') || $request->user()->can('articles.preview'), 403);

        return $this->inlineImageResponse($article, $media, 'private, no-store');
    }

    public function publicInlineImage(Article $article, int $media)
    {
        abort_unless($article->status === 'published' && $article->archived_at === null && $article->published_at !== null, 404);
        abort_unless($this->hasValidContent($article), 404);
        abort_unless(in_array($media, $this->referencedInlineMediaIds($article), true), 404);

        return $this->inlineImageResponse($article, $media, 'no-store');
    }

    private function inlineImageResponse(Article $article, int $mediaId, string $cacheControl)
    {
        $media = $article->inlineMedia()->findOrFail($mediaId);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($media->path) && $disk->mimeType($media->path) === $media->mime_type && in_array($media->mime_type, ['image/jpeg', 'image/png'], true), 404);

        return $disk->response($media->path, null, [
            'Content-Type' => $media->mime_type,
            'Cache-Control' => $cacheControl,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function update(UpdateArticleRequest $request, Article $article, AuditLogger $auditLogger, ArticleCoverImageService $coverImages, ArticleInlineImageService $inlineImages)
    {
        $data = $request->validated();
        $image = $data['cover_image'] ?? null;
        $removeImage = $data['remove_cover_image'] ?? false;
        unset($data['cover_image'], $data['remove_cover_image']);
        $oldCoverPath = null;
        $newCoverPath = null;
        $removedInlinePaths = [];
        if ($image !== null) {
            $coverImages->inspect($image);
        }

        try {
            DB::transaction(function () use ($article, $data, $image, $removeImage, $auditLogger, $coverImages, &$oldCoverPath, &$newCoverPath, &$removedInlinePaths): void {
                $article = Article::query()->lockForUpdate()->findOrFail($article->id);
                $oldCoverPath = $article->cover_image_path;
                $article->update($data);
                $auditLogger->log('article.updated', $article);

                $usedMediaIds = array_values(array_unique($this->referencedInlineMediaIdsFromContent($article->content)));
                $unusedMedia = $article->inlineMedia()->whereNotIn('id', $usedMediaIds ?: [-1])->get();
                if ($unusedMedia->isNotEmpty()) {
                    $removedInlinePaths = $unusedMedia->pluck('path')->all();
                    $article->inlineMedia()->whereKey($unusedMedia->modelKeys())->delete();
                    $auditLogger->log('article.inline_image_removed', $article);
                }

                if ($image !== null) {
                    $newCoverPath = $coverImages->store($article, $image);
                    $article->forceFill(['cover_image_path' => $newCoverPath])->save();
                    $auditLogger->log('article.cover_image_uploaded', $article);
                } elseif ($removeImage && $article->cover_image_path !== null) {
                    $article->forceFill(['cover_image_path' => null])->save();
                    $auditLogger->log('article.cover_image_removed', $article);
                }
            });
        } catch (Throwable $exception) {
            $coverImages->delete($newCoverPath);
            throw $exception;
        }

        if ($image !== null || $removeImage) {
            $coverImages->delete($oldCoverPath);
        }
        foreach ($removedInlinePaths as $path) {
            $inlineImages->delete($path);
        }

        return to_route('articles.index')->with('status', 'Article draft updated.');
    }

    public function preview(Request $request, Article $article): Response
    {
        abort_unless($article->status === 'draft', 404);
        abort_unless($this->hasValidContent($article), 404);

        return Inertia::render('Articles/Preview', [
            'article' => [
                'id' => $article->id,
                'title' => $article->title,
                'content' => $article->content,
                'author' => $article->author?->display_name ?? 'Deleted account',
                'updated_at' => $article->updated_at?->toIso8601String(),
            ],
            'can_update_articles' => $request->user()->can('articles.update'),
        ]);
    }

    public function publicIndex(): Response
    {
        $articles = Article::query()
            ->with('author')
            ->where('status', 'published')
            ->whereNull('archived_at')
            ->whereNotNull('published_at')
            ->orderByDesc('published_at')
            ->paginate(20)
            ->through(fn (Article $article): array => [
                'id' => $article->id,
                'title' => $article->title,
                'cover_image_url' => $article->cover_image_path ? "/blog/{$article->id}/cover" : null,
                'author' => $article->author?->display_name ?? 'Deleted account',
                'published_at' => $article->published_at?->toIso8601String(),
            ]);

        return Inertia::render('Blog/Index', ['articles' => $articles]);
    }

    public function publicShow(Article $article): Response
    {
        abort_unless($article->status === 'published' && $article->archived_at === null && $article->published_at !== null, 404);
        abort_unless($this->hasValidContent($article), 404);

        return Inertia::render('Blog/Show', [
            'article' => [
                'id' => $article->id,
                'title' => $article->title,
                'content' => $article->content,
                'cover_image_url' => $article->cover_image_path ? "/blog/{$article->id}/cover" : null,
                'author' => $article->author?->display_name ?? 'Deleted account',
                'published_at' => $article->published_at->toIso8601String(),
            ],
        ]);
    }

    public function privateCover(Article $article)
    {
        abort_unless($article->cover_image_path !== null, 404);

        return $this->coverResponse($article, 'private, no-store');
    }

    public function publicCover(Article $article)
    {
        abort_unless($article->status === 'published' && $article->archived_at === null && $article->published_at !== null, 404);
        abort_unless($article->cover_image_path !== null, 404);

        return $this->coverResponse($article, 'no-store');
    }

    private function coverResponse(Article $article, string $cacheControl)
    {
        $disk = Storage::disk('local');
        abort_unless($disk->exists($article->cover_image_path), 404);
        $mime = $disk->mimeType($article->cover_image_path);
        abort_unless(in_array($mime, ['image/jpeg', 'image/png'], true), 404);

        return $disk->response($article->cover_image_path, null, [
            'Content-Type' => $mime,
            'Cache-Control' => $cacheControl,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function publish(Article $article, AuditLogger $auditLogger)
    {
        DB::transaction(function () use ($article, $auditLogger): void {
            $article = Article::query()->lockForUpdate()->findOrFail($article->id);
            abort_unless($article->status === 'draft' && $article->archived_at === null, 409);
            abort_unless($this->hasValidContent($article), 422);

            $article->forceFill(['status' => 'published', 'published_at' => now()])->save();
            $auditLogger->log('article.published', $article);
        });

        return to_route('articles.index')->with('status', 'Article published.');
    }

    public function unpublish(Article $article, AuditLogger $auditLogger)
    {
        DB::transaction(function () use ($article, $auditLogger): void {
            $article = Article::query()->lockForUpdate()->findOrFail($article->id);
            abort_unless($article->status === 'published', 409);

            $article->forceFill(['status' => 'draft', 'published_at' => null])->save();
            $auditLogger->log('article.unpublished', $article);
        });

        return to_route('articles.index')->with('status', 'Article returned to drafts.');
    }

    private function hasValidContent(Article $article): bool
    {
        if (Validator::make(['content' => $article->content], [
            'content' => ['required', 'array', new ArticleDocument],
        ])->fails()) {
            return false;
        }

        $mediaIds = array_values(array_unique($this->referencedInlineMediaIds($article)));

        return $mediaIds === [] || $article->inlineMedia()->whereIn('id', $mediaIds)->count() === count($mediaIds);
    }

    /** @return list<int> */
    private function referencedInlineMediaIds(Article $article): array
    {
        return $this->referencedInlineMediaIdsFromContent($article->content);
    }

    /** @return list<int> */
    private function referencedInlineMediaIdsFromContent(array $content): array
    {
        $mediaIds = [];
        $collect = function (array $nodes) use (&$collect, &$mediaIds): void {
            foreach ($nodes as $node) {
                if (($node['type'] ?? null) === 'articleImage') {
                    $mediaIds[] = $node['attrs']['mediaId'];
                }
                if (is_array($node['content'] ?? null)) {
                    $collect($node['content']);
                }
            }
        };
        $collect($content['content'] ?? []);

        return $mediaIds;
    }
}
