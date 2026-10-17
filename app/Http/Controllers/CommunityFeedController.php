<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeleteCommunityCommentRequest;
use App\Http\Requests\DeleteCommunityPostMediaRequest;
use App\Http\Requests\DeleteCommunityPostRequest;
use App\Http\Requests\LikeCommunityPostRequest;
use App\Http\Requests\ModerateCommunityCommentRequest;
use App\Http\Requests\ModerateCommunityPostRequest;
use App\Http\Requests\StoreCommunityCommentRequest;
use App\Http\Requests\StoreCommunityPostMediaRequest;
use App\Http\Requests\StoreCommunityPostRequest;
use App\Http\Requests\StoreCommunityReplyRequest;
use App\Http\Requests\UpdateCommunityCommentRequest;
use App\Http\Requests\UpdateCommunityPostRequest;
use App\Models\CommunityComment;
use App\Models\CommunityPost;
use App\Models\CommunityPostMedia;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\CommunityPostMediaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CommunityFeedController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate(['view' => ['nullable', Rule::in(['active', 'archived', 'moderated'])]]);
        $view = $filters['view'] ?? 'active';
        $canModeratePosts = $request->user()->can('community-feed.moderate_posts');
        $canModerateComments = $request->user()->can('community-feed.moderate_comments');
        abort_if($view === 'moderated' && ! $canModeratePosts, 403);
        $posts = CommunityPost::query()
            ->with(['author', 'media'])
            ->withCount(['comments' => fn ($query) => $query->when(! $canModerateComments, fn ($query) => $query->whereNull('moderated_at'))])
            ->withCount('likes')
            ->withExists(['likes as liked_by_user' => fn ($query) => $query->where('user_id', $request->user()->id)])
            ->when($view === 'moderated', fn ($query) => $query->whereNotNull('moderated_at'), fn ($query) => $query
                ->whereNull('moderated_at')
                ->when($view === 'archived', fn ($query) => $query->whereNotNull('archived_at'), fn ($query) => $query->whereNull('archived_at')))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->through(function (CommunityPost $post) use ($request, $canModeratePosts): array {
                $isAuthor = $post->author_id === $request->user()->id;

                return [
                    'id' => $post->id,
                    'body' => $post->body,
                    'author' => $post->author?->display_name ?? 'Deleted account',
                    'created_at' => $post->created_at?->toIso8601String(),
                    'archived_at' => $post->archived_at?->toIso8601String(),
                    'moderated_at' => $post->moderated_at?->toIso8601String(),
                    'can_update' => $isAuthor && $request->user()->can('community-feed.update'),
                    'can_delete' => $isAuthor && $request->user()->can('community-feed.delete'),
                    'can_archive' => $request->user()->can('community-feed.archive'),
                    'comments_count' => $post->comments_count,
                    'likes_count' => $post->likes_count,
                    'liked_by_user' => (bool) $post->liked_by_user,
                    'can_like' => $post->archived_at === null && $post->moderated_at === null && $request->user()->can('community-feed.like'),
                    'can_moderate' => $canModeratePosts,
                    'media' => $post->media->map(fn (CommunityPostMedia $media): array => [
                        'id' => $media->id,
                        'name' => $media->original_name,
                        'url' => route('community.media', [$post, $media]),
                    ])->values(),
                ];
            });

        return Inertia::render('Community/Index', [
            'posts' => $posts,
            'view' => $view,
            'can_create_post' => $request->user()->can('community-feed.create'),
            'can_view_trash' => $request->user()->can('community-feed.view_trash'),
            'can_view_moderated' => $canModeratePosts,
        ]);
    }

    public function trash(Request $request): Response
    {
        $posts = CommunityPost::onlyTrashed()
            ->with('author')
            ->orderByDesc('deleted_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->through(fn (CommunityPost $post): array => [
                'id' => $post->id,
                'body' => $post->body,
                'author' => $post->author?->display_name ?? 'Deleted account',
                'deleted_at' => $post->deleted_at?->toIso8601String(),
                'archived_at' => $post->archived_at?->toIso8601String(),
            ]);

        return Inertia::render('Community/Trash', [
            'posts' => $posts,
            'can_restore_posts' => $request->user()->can('community-feed.restore'),
            'can_force_delete_posts' => $request->user()->can('community-feed.force-delete'),
        ]);
    }

    public function comments(Request $request, CommunityPost $post): Response
    {
        $canModerateComments = $request->user()->can('community-feed.moderate_comments');
        abort_if($post->moderated_at !== null && ! $request->user()->can('community-feed.moderate_posts'), 404);
        $comments = $post->comments()
            ->whereNull('parent_comment_id')
            ->when(! $canModerateComments, fn ($query) => $query->whereNull('moderated_at'))
            ->with([
                'author',
                'replies' => fn ($query) => $query->when(! $canModerateComments, fn ($query) => $query->whereNull('moderated_at')),
                'replies.author',
            ])
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (CommunityComment $comment): array => $this->commentData($comment, $request->user(), $post->archived_at === null && $post->moderated_at === null, $canModerateComments));

        return Inertia::render('Community/Comments', [
            'post' => [
                'id' => $post->id,
                'body' => $post->body,
                'author' => $post->author?->display_name ?? 'Deleted account',
                'archived' => $post->archived_at !== null,
                'moderated' => $post->moderated_at !== null,
            ],
            'comments' => $comments,
            'can_comment' => $post->archived_at === null && $post->moderated_at === null && $request->user()->can('community-feed.comment'),
            'can_moderate_comments' => $canModerateComments,
        ]);
    }

    public function store(StoreCommunityPostRequest $request, AuditLogger $auditLogger, CommunityPostMediaService $mediaService): RedirectResponse
    {
        DB::transaction(function () use ($request, $auditLogger, $mediaService): void {
            $post = CommunityPost::create([
                'author_id' => $request->user()->id,
                'body' => $request->validated('body'),
            ]);
            $auditLogger->log('community.post_created', $post);
            $mediaService->store($post, $request->user(), $request->file('media', []), $auditLogger);
        });

        return to_route('community.index')->with('status', 'Post shared with your team.');
    }

    public function media(Request $request, CommunityPost $post, CommunityPostMedia $media): StreamedResponse
    {
        abort_unless($media->community_post_id === $post->id, 404);
        abort_if($post->moderated_at !== null && ! $request->user()->can('community-feed.moderate_posts'), 404);

        return Storage::disk('local')->response($media->path, $media->original_name, [
            'Content-Type' => $media->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ], 'inline');
    }

    public function deleteMedia(DeleteCommunityPostMediaRequest $request, CommunityPost $post, CommunityPostMedia $media, CommunityPostMediaService $mediaService, AuditLogger $auditLogger): RedirectResponse
    {
        $mediaService->delete($media, $auditLogger);

        return to_route('community.index')->with('status', 'Image removed from the post.');
    }

    public function storeMedia(StoreCommunityPostMediaRequest $request, CommunityPost $post, CommunityPostMediaService $mediaService, AuditLogger $auditLogger): RedirectResponse
    {
        DB::transaction(function () use ($request, $post, $mediaService, $auditLogger): void {
            $post = CommunityPost::query()->lockForUpdate()->findOrFail($post->id);
            $files = $request->file('media', []);
            if ($post->media()->count() + count($files) > 4) {
                throw ValidationException::withMessages(['media' => 'A post can have up to four images.']);
            }

            $mediaService->store($post, $request->user(), $files, $auditLogger);
        });

        return to_route('community.index')->with('status', 'Images added to the post.');
    }

    public function like(LikeCommunityPostRequest $request, CommunityPost $post, AuditLogger $auditLogger): RedirectResponse
    {
        DB::transaction(function () use ($request, $post, $auditLogger): void {
            $post = CommunityPost::query()->lockForUpdate()->findOrFail($post->id);
            abort_unless($post->archived_at === null, 409);
            $like = $post->likes()->firstOrCreate(['user_id' => $request->user()->id]);

            if ($like->wasRecentlyCreated) {
                $auditLogger->log('community.post_liked', $like);
            }
        });

        return to_route('community.index')->with('status', 'Post liked.');
    }

    public function unlike(LikeCommunityPostRequest $request, CommunityPost $post, AuditLogger $auditLogger): RedirectResponse
    {
        DB::transaction(function () use ($request, $post, $auditLogger): void {
            $post = CommunityPost::query()->lockForUpdate()->findOrFail($post->id);
            abort_unless($post->archived_at === null, 409);
            $like = $post->likes()->where('user_id', $request->user()->id)->first();

            if ($like !== null) {
                $auditLogger->log('community.post_unliked', $like);
                $like->delete();
            }
        });

        return to_route('community.index')->with('status', 'Like removed.');
    }

    public function storeComment(StoreCommunityCommentRequest $request, CommunityPost $post, AuditLogger $auditLogger): RedirectResponse
    {
        DB::transaction(function () use ($request, $post, $auditLogger): void {
            $comment = $post->comments()->create([
                'author_id' => $request->user()->id,
                'body' => $request->validated('body'),
            ]);
            $auditLogger->log('community.comment_created', $comment);
        });

        return to_route('community.comments', $post)->with('status', 'Comment added.');
    }

    public function storeReply(StoreCommunityReplyRequest $request, CommunityPost $post, CommunityComment $comment, AuditLogger $auditLogger): RedirectResponse
    {
        DB::transaction(function () use ($request, $post, $comment, $auditLogger): void {
            $reply = $comment->replies()->create([
                'community_post_id' => $post->id,
                'author_id' => $request->user()->id,
                'body' => $request->validated('body'),
            ]);
            $auditLogger->log('community.reply_created', $reply);
        });

        return to_route('community.comments', $post)->with('status', 'Reply added.');
    }

    /** @return array<string, mixed> */
    private function commentData(CommunityComment $comment, User $viewer, bool $allowReply = false): array
    {
        $isAuthor = $comment->author_id === $viewer->id;

        return [
            'id' => $comment->id,
            'body' => $comment->body,
            'author' => $comment->author?->display_name ?? 'Deleted account',
            'created_at' => $comment->created_at?->toIso8601String(),
            'can_update' => $isAuthor && $viewer->can('community-feed.update_comment'),
            'can_delete' => $isAuthor && $viewer->can('community-feed.delete_comment'),
            'can_reply' => $allowReply && $comment->moderated_at === null && $viewer->can('community-feed.reply'),
            'moderated' => $comment->moderated_at !== null,
            'can_moderate' => $viewer->can('community-feed.moderate_comments'),
            'replies' => $allowReply
                ? $comment->replies->map(fn (CommunityComment $reply): array => $this->commentData($reply, $viewer, false))->values()
                : [],
        ];
    }

    public function moderatePost(ModerateCommunityPostRequest $request, CommunityPost $post, AuditLogger $auditLogger): RedirectResponse
    {
        $hide = $request->validated('action') === 'hide';

        DB::transaction(function () use ($request, $post, $auditLogger, $hide): void {
            $post = CommunityPost::query()->lockForUpdate()->findOrFail($post->id);
            if (($post->moderated_at !== null) !== $hide) {
                $post->forceFill([
                    'moderated_at' => $hide ? now() : null,
                    'moderated_by' => $hide ? $request->user()->id : null,
                ])->save();
                $auditLogger->log($hide ? 'community.post_moderated' : 'community.post_unmoderated', $post);
            }
        });

        if ($hide) {
            return to_route('community.index', ['view' => 'moderated'])->with('status', 'Post hidden from the feed.');
        }

        return to_route('community.index', $post->archived_at === null ? [] : ['view' => 'archived'])->with('status', 'Post restored to the feed.');
    }

    public function moderateComment(ModerateCommunityCommentRequest $request, CommunityPost $post, CommunityComment $comment, AuditLogger $auditLogger): RedirectResponse
    {
        $hide = $request->validated('action') === 'hide';

        DB::transaction(function () use ($request, $post, $comment, $auditLogger, $hide): void {
            $comment = CommunityComment::query()->lockForUpdate()->findOrFail($comment->id);
            abort_unless($comment->community_post_id === $post->id, 404);
            if (($comment->moderated_at !== null) !== $hide) {
                $comment->forceFill([
                    'moderated_at' => $hide ? now() : null,
                    'moderated_by' => $hide ? $request->user()->id : null,
                ])->save();
                $auditLogger->log($hide ? 'community.comment_moderated' : 'community.comment_unmoderated', $comment);
            }
        });

        return to_route('community.comments', $post)->with('status', $hide ? 'Comment hidden.' : 'Comment restored.');
    }

    public function updateComment(UpdateCommunityCommentRequest $request, CommunityPost $post, CommunityComment $comment, AuditLogger $auditLogger): RedirectResponse
    {
        DB::transaction(function () use ($request, $comment, $auditLogger): void {
            $comment->update(['body' => $request->validated('body')]);
            $auditLogger->log('community.comment_updated', $comment);
        });

        return to_route('community.comments', $post)->with('status', 'Comment updated.');
    }

    public function destroyComment(DeleteCommunityCommentRequest $request, CommunityPost $post, CommunityComment $comment, AuditLogger $auditLogger): RedirectResponse
    {
        DB::transaction(function () use ($comment, $auditLogger): void {
            $auditLogger->log('community.comment_deleted', $comment);
            $comment->delete();
        });

        return to_route('community.comments', $post)->with('status', 'Comment deleted.');
    }

    public function update(UpdateCommunityPostRequest $request, CommunityPost $post, AuditLogger $auditLogger): RedirectResponse
    {
        DB::transaction(function () use ($request, $post, $auditLogger): void {
            $post->update(['body' => $request->validated('body')]);
            $auditLogger->log('community.post_updated', $post);
        });

        return to_route('community.index')->with('status', 'Post updated.');
    }

    public function destroy(DeleteCommunityPostRequest $request, CommunityPost $post, AuditLogger $auditLogger): RedirectResponse
    {
        DB::transaction(function () use ($post, $auditLogger): void {
            $post->delete();
            $auditLogger->log('community.post_trashed', $post);
        });

        return to_route('community.index')->with('status', 'Post moved to trash.');
    }

    public function archive(CommunityPost $post, AuditLogger $auditLogger): RedirectResponse
    {
        DB::transaction(function () use ($post, $auditLogger): void {
            $post = CommunityPost::query()->lockForUpdate()->findOrFail($post->id);
            abort_unless($post->archived_at === null, 409);
            $post->forceFill(['archived_at' => now()])->save();
            $auditLogger->log('community.post_archived', $post);
        });

        return to_route('community.index')->with('status', 'Post archived.');
    }

    public function unarchive(CommunityPost $post, AuditLogger $auditLogger): RedirectResponse
    {
        DB::transaction(function () use ($post, $auditLogger): void {
            $post = CommunityPost::query()->lockForUpdate()->findOrFail($post->id);
            abort_unless($post->archived_at !== null, 409);
            $post->forceFill(['archived_at' => null])->save();
            $auditLogger->log('community.post_unarchived', $post);
        });

        return to_route('community.index', ['view' => 'archived'])->with('status', 'Post restored from archive.');
    }

    public function restore(int $post, AuditLogger $auditLogger): RedirectResponse
    {
        DB::transaction(function () use ($post, $auditLogger): void {
            $post = CommunityPost::withTrashed()->lockForUpdate()->findOrFail($post);
            abort_unless($post->trashed(), 409);
            $post->restore();
            $auditLogger->log('community.post_restored', $post);
        });

        return to_route('community.trash')->with('status', 'Post restored from trash.');
    }

    public function forceDelete(int $post, AuditLogger $auditLogger, CommunityPostMediaService $mediaService): RedirectResponse
    {
        DB::transaction(function () use ($post, $auditLogger, $mediaService): void {
            $post = CommunityPost::withTrashed()->lockForUpdate()->findOrFail($post);
            abort_unless($post->trashed(), 409);
            $auditLogger->log('community.post_force_deleted', $post);
            $mediaService->purge($post);
            $post->forceDelete();
        });

        return to_route('community.trash')->with('status', 'Post permanently deleted.');
    }
}
