import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import ConfirmDialog from '../../Components/ConfirmDialog';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';

type Post = { id: number; body: string; author: string; created_at: string | null; archived_at: string | null; moderated_at: string | null; comments_count: number; likes_count: number; liked_by_user: boolean; can_like: boolean; can_update: boolean; can_delete: boolean; can_archive: boolean; can_moderate: boolean; media: { id: number; name: string; url: string }[] };
type PostsPage = { data: Post[]; current_page: number; last_page: number; total: number };

function PostCard({ post }: { post: Post }) {
    const [editing, setEditing] = useState(false);
    const [confirmDelete, setConfirmDelete] = useState(false);
    const [mediaToRemove, setMediaToRemove] = useState<{ id: number; name: string } | null>(null);
    const [archiveAction, setArchiveAction] = useState<'archive' | 'unarchive' | null>(null);
    const [deleting, setDeleting] = useState(false);
    const form = useForm({ body: post.body });
    const mediaForm = useForm<{ media: File[] }>({ media: [] });
    const likeForm = useForm({});
    const mediaError = mediaForm.errors.media || mediaForm.errors['media.0'] || mediaForm.errors['media.1'] || mediaForm.errors['media.2'] || mediaForm.errors['media.3'];

    return (
        <article className="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-sm sm:p-6">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="flex min-w-0 items-center gap-3">
                    <span aria-hidden="true" className="grid size-10 shrink-0 place-items-center rounded-full bg-brand/10 text-sm font-bold text-brand">{post.author.slice(0, 1).toUpperCase()}</span>
                    <div className="min-w-0">
                        <p className="truncate text-sm font-semibold text-slate-900 dark:text-slate-100">{post.author}</p>
                        <time className="text-xs text-slate-500 dark:text-slate-400" dateTime={post.created_at ?? undefined}>{post.created_at ? new Date(post.created_at).toLocaleString() : 'Date unavailable'}</time>
                    </div>
                </div>
                {!editing && (post.can_update || post.can_delete || post.can_archive || post.can_moderate) && <div className="flex flex-wrap items-center gap-2">
                    {post.can_update && <button className="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-100 focus:outline-none focus:ring-4 focus:ring-slate-100 dark:focus:ring-slate-700" onClick={() => { form.setData('body', post.body); form.clearErrors(); setEditing(true); }} type="button">Edit</button>}
                    {post.can_delete && <button className="rounded-lg px-3 py-2 text-sm font-semibold text-red-700 dark:text-red-300 hover:bg-red-50 dark:hover:bg-red-950 focus:outline-none focus:ring-4 focus:ring-red-100 dark:focus:ring-red-900" onClick={() => setConfirmDelete(true)} type="button">Delete</button>}
                    {post.can_archive && <button className="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-100 focus:outline-none focus:ring-4 focus:ring-slate-100 dark:focus:ring-slate-700" onClick={() => setArchiveAction(post.archived_at ? 'unarchive' : 'archive')} type="button">{post.archived_at ? 'Unarchive' : 'Archive'}</button>}
                    {post.can_moderate && <button className="rounded-lg px-3 py-2 text-sm font-medium text-amber-800 dark:text-amber-300 hover:bg-amber-50 dark:hover:bg-amber-900 focus:outline-none focus:ring-4 focus:ring-amber-100 dark:focus:ring-amber-900" onClick={() => router.patch(`/community/${post.id}/moderation`, { action: post.moderated_at ? 'restore' : 'hide' }, { preserveScroll: true })} type="button">{post.moderated_at ? 'Restore post' : 'Hide post'}</button>}
                </div>}
            </div>
            {post.moderated_at && <p className="mt-3 inline-flex rounded-full bg-amber-50 dark:bg-amber-950 px-2.5 py-1 text-xs font-semibold text-amber-800 dark:text-amber-300">Hidden by moderation</p>}
            {editing ? <form className="mt-4" onSubmit={(event) => {
                event.preventDefault();
                form.put(`/community/${post.id}`, { onSuccess: () => setEditing(false) });
            }}>
                <label className="sr-only" htmlFor={`community-post-${post.id}`}>Edit post</label>
                <textarea aria-describedby={form.errors.body ? `community-post-${post.id}-error` : undefined} aria-invalid={Boolean(form.errors.body)} className="block min-h-28 w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 p-3 text-sm text-slate-900 dark:text-slate-100 focus:border-brand focus:ring-brand" id={`community-post-${post.id}`} maxLength={5000} onChange={(event) => form.setData('body', event.target.value)} value={form.data.body} />
                {form.errors.body && <p className="mt-2 text-sm text-red-700 dark:text-red-300" id={`community-post-${post.id}-error`} role="alert">{form.errors.body}</p>}
                <div className="mt-3 flex justify-end gap-2">
                    <button className="rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800" onClick={() => { form.setData('body', post.body); form.clearErrors(); setEditing(false); }} type="button">Cancel</button>
                    <button className="rounded-lg bg-brand px-3 py-2 text-sm font-semibold text-white hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-60" disabled={form.processing || !form.data.body.trim()} type="submit">{form.processing ? 'Saving…' : 'Save changes'}</button>
                </div>
            </form> : <p className="mt-4 whitespace-pre-wrap break-words text-sm leading-7 text-slate-700 dark:text-slate-200">{post.body}</p>}
            {post.media.length > 0 && <div className="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3">{post.media.map((media) => <div className="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800" key={media.id}><a className="group block" href={media.url} rel="noreferrer" target="_blank"><img alt={media.name} className="aspect-square w-full object-cover transition group-hover:scale-[1.02]" loading="lazy" src={media.url} /></a><div className="flex items-center justify-between gap-2 px-2 py-1.5"><span className="min-w-0 truncate text-xs text-slate-500 dark:text-slate-400">{media.name}</span>{post.can_update && <button aria-label={`Remove ${media.name}`} className="shrink-0 rounded px-1.5 py-1 text-xs font-semibold text-red-700 dark:text-red-300 hover:bg-red-50 dark:hover:bg-red-950 focus:outline-none focus:ring-2 focus:ring-red-200 dark:focus:ring-red-800" onClick={() => setMediaToRemove({ id: media.id, name: media.name })} type="button">Remove</button>}</div></div>)}</div>}
            {post.can_update && post.media.length < 4 && <form className="mt-4 rounded-xl border border-dashed border-slate-300 dark:border-slate-700 p-3" onSubmit={(event) => {
                event.preventDefault();
                mediaForm.post(`/community/${post.id}/media`, { forceFormData: true, preserveScroll: true, onSuccess: () => mediaForm.reset() });
            }}>
                <label className="block text-sm font-medium text-slate-700 dark:text-slate-200" htmlFor={`community-post-media-${post.id}`}>Add images <span className="font-normal text-slate-500 dark:text-slate-400">({4 - post.media.length} remaining, PNG/JPEG, up to 10 MB each)</span></label>
                <div className="mt-2 flex flex-wrap items-center gap-3">
                    <input accept=".jpg,.jpeg,.png,image/jpeg,image/png" aria-describedby={mediaError ? `community-post-media-${post.id}-error` : undefined} aria-invalid={Boolean(mediaError)} className="min-w-0 flex-1 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm text-slate-700 dark:text-slate-200 file:mr-3 file:border-0 file:bg-slate-100 dark:file:bg-slate-700 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-slate-700 dark:file:text-slate-200" id={`community-post-media-${post.id}`} multiple onChange={(event) => mediaForm.setData('media', Array.from(event.target.files ?? []))} type="file" />
                    <button className="rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60" disabled={mediaForm.processing || mediaForm.data.media.length === 0} type="submit">{mediaForm.processing ? 'Uploading…' : 'Upload images'}</button>
                </div>
                {mediaError && <p className="mt-2 text-sm text-red-700 dark:text-red-300" id={`community-post-media-${post.id}-error`} role="alert">{mediaError}</p>}
            </form>}
            <div className="mt-4 flex flex-wrap items-center gap-x-5 gap-y-2 border-t border-slate-100 dark:border-slate-800 pt-3">
                {post.can_like ? <button aria-pressed={post.liked_by_user} className={`inline-flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm font-semibold transition hover:bg-slate-50 dark:hover:bg-slate-800 focus:outline-none focus:ring-4 focus:ring-brand/10 disabled:opacity-60 ${post.liked_by_user ? 'text-brand' : 'text-slate-600 dark:text-slate-300'}`} disabled={likeForm.processing} onClick={() => {
                    if (post.liked_by_user) {
                        likeForm.delete(`/community/${post.id}/like`, { preserveScroll: true });
                    } else {
                        likeForm.post(`/community/${post.id}/like`, { preserveScroll: true });
                    }
                }} type="button"><span aria-hidden="true" className="text-lg leading-none">{post.liked_by_user ? '♥' : '♡'}</span>{post.liked_by_user ? 'Liked' : 'Like'} <span className="font-normal text-slate-500 dark:text-slate-400">{post.likes_count.toLocaleString()}</span></button> : <span className="text-sm text-slate-500 dark:text-slate-400"><span aria-hidden="true" className="mr-2 text-brand">♥</span>{post.likes_count.toLocaleString()} {post.likes_count === 1 ? 'like' : 'likes'}</span>}
                <Link className="text-sm font-semibold text-brand hover:underline" href={`/community/${post.id}/comments`}>Comments ({post.comments_count})</Link>
            </div>
            <ConfirmDialog
                cancelLabel="Keep post"
                confirmLabel={deleting ? 'Moving…' : 'Move to trash'}
                description="This post will be removed from the community feed."
                onCancel={() => setConfirmDelete(false)}
                onConfirm={() => router.delete(`/community/${post.id}`, { onStart: () => setDeleting(true), onFinish: () => setDeleting(false), onSuccess: () => setConfirmDelete(false) })}
                open={confirmDelete}
                title="Move post to trash?"
            />
            <ConfirmDialog
                confirmLabel={archiveAction === 'unarchive' ? 'Unarchive post' : 'Archive post'}
                description={archiveAction === 'unarchive' ? 'This post will return to the active community feed.' : 'This post will leave the active community feed but remain available in Archived.'}
                onCancel={() => setArchiveAction(null)}
                onConfirm={() => archiveAction && router.patch(`/community/${post.id}/${archiveAction}`, {}, { onFinish: () => setArchiveAction(null) })}
                open={archiveAction !== null}
                title={archiveAction === 'unarchive' ? 'Unarchive this post?' : 'Archive this post?'}
                tone="primary"
            />
            <ConfirmDialog
                confirmLabel="Remove image"
                description={<>“{mediaToRemove?.name}” will be permanently removed from this post.</>}
                onCancel={() => setMediaToRemove(null)}
                onConfirm={() => {
                    if (mediaToRemove) {
                        const mediaId = mediaToRemove.id;
                        setMediaToRemove(null);
                        router.delete(`/community/${post.id}/media/${mediaId}`, { preserveScroll: true });
                    }
                }}
                open={mediaToRemove !== null}
                title="Remove this image?"
            />
        </article>
    );
}

export default function Index({ auth, posts, view, can_create_post, can_view_trash, can_view_moderated }: { auth: { user: { display_name: string; email: string; can_manage_users: boolean } | null }; posts: PostsPage; view: 'active' | 'archived' | 'moderated'; can_create_post: boolean; can_view_trash: boolean; can_view_moderated: boolean }) {
    const pageHref = (page: number) => `/community?${view === 'active' ? '' : `view=${view}&`}page=${page}`;
    const form = useForm<{ body: string; media: File[] }>({ body: '', media: [] });
    const mediaError = form.errors.media || form.errors['media.0'] || form.errors['media.1'] || form.errors['media.2'] || form.errors['media.3'];

    return (
        <AuthenticatedLayout user={auth.user}>
            <Head title="Community" />
            <div className="mx-auto w-full max-w-5xl space-y-5">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p className="text-sm font-semibold text-brand">Workspace</p>
                        <h1 className="mt-1 font-brand text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">{view === 'archived' ? 'Archived posts' : view === 'moderated' ? 'Moderated posts' : 'Community feed'}</h1>
                        <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">{view === 'archived' ? 'Posts removed from the active feed but kept for reference.' : view === 'moderated' ? 'Posts hidden from the feed for moderator review.' : 'Updates shared with your team.'}</p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        {can_view_trash && <Link className="rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800" href="/community/trash">Trash</Link>}
                        <p className="px-2 text-sm text-slate-500 dark:text-slate-400">{posts.total} {posts.total === 1 ? 'post' : 'posts'}</p>
                    </div>
                </div>

                <nav aria-label="Community post views" className="flex gap-2 border-b border-slate-200 dark:border-slate-800">
                    <Link aria-current={view === 'active' ? 'page' : undefined} className={`border-b-2 px-3 py-2 text-sm font-semibold ${view === 'active' ? 'border-brand text-brand' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-100'}`} href="/community">Active</Link>
                    <Link aria-current={view === 'archived' ? 'page' : undefined} className={`border-b-2 px-3 py-2 text-sm font-semibold ${view === 'archived' ? 'border-brand text-brand' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-100'}`} href="/community?view=archived">Archived</Link>
                    {can_view_moderated && <Link aria-current={view === 'moderated' ? 'page' : undefined} className={`border-b-2 px-3 py-2 text-sm font-semibold ${view === 'moderated' ? 'border-brand text-brand' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-100'}`} href="/community?view=moderated">Moderated</Link>}
                </nav>

                {view === 'active' && can_create_post && <form className="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-sm sm:p-6" onSubmit={(event) => {
                    event.preventDefault();
                    form.post('/community', { forceFormData: true, onSuccess: () => form.reset() });
                }}>
                    <label className="mb-2 block text-sm font-semibold text-slate-900 dark:text-slate-100" htmlFor="community-post">Share an update</label>
                    <textarea aria-describedby={`community-post-hint${form.errors.body ? ' community-post-error' : ''}`} aria-invalid={Boolean(form.errors.body)} className="block min-h-28 w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 p-3 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 focus:border-brand focus:ring-brand" id="community-post" maxLength={5000} onChange={(event) => form.setData('body', event.target.value)} placeholder="Share an update with your team…" value={form.data.body} />
                    <label className="mt-3 block text-sm font-medium text-slate-700 dark:text-slate-200" htmlFor="community-post-media">Attach images <span className="font-normal text-slate-500 dark:text-slate-400">(up to 4 PNG or JPEG files, 10 MB each)</span></label>
                    <input accept=".jpg,.jpeg,.png,image/jpeg,image/png" aria-describedby={mediaError ? 'community-post-media-error' : undefined} aria-invalid={Boolean(mediaError)} className="mt-1 block w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm text-slate-700 dark:text-slate-200 file:mr-4 file:border-0 file:bg-slate-100 dark:file:bg-slate-700 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-slate-700 dark:file:text-slate-200" id="community-post-media" multiple onChange={(event) => form.setData('media', Array.from(event.target.files ?? []))} type="file" />
                    <div className="mt-2 flex flex-wrap items-center justify-between gap-3">
                        <p className="text-xs text-slate-500 dark:text-slate-400" id="community-post-hint">Up to 5,000 characters. Posts are visible to feed readers.</p>
                        <button className="rounded-lg bg-brand px-4 py-2.5 text-sm font-semibold text-white transition hover:brightness-95 focus:outline-none focus:ring-4 focus:ring-brand/20 disabled:cursor-not-allowed disabled:opacity-60" disabled={form.processing || !form.data.body.trim()} type="submit">{form.processing ? 'Sharing…' : 'Share update'}</button>
                    </div>
                    {form.errors.body && <p className="mt-2 text-sm text-red-700 dark:text-red-300" id="community-post-error" role="alert">{form.errors.body}</p>}
                    {mediaError && <p className="mt-2 text-sm text-red-700 dark:text-red-300" id="community-post-media-error" role="alert">{mediaError}</p>}
                </form>}

                {posts.data.length ? <div className="space-y-4">
                    {posts.data.map((post) => <PostCard key={post.id} post={post} />)}
                    {posts.last_page > 1 && <div className="flex items-center justify-between gap-3 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-4 py-3 text-sm">
                        <span className="text-slate-500 dark:text-slate-400">Page {posts.current_page} of {posts.last_page}</span>
                        <nav aria-label="Community post pages" className="flex flex-wrap gap-2">
                            <Link aria-disabled={posts.current_page <= 1} className={`rounded-lg border px-3 py-2 font-medium ${posts.current_page <= 1 ? 'pointer-events-none border-slate-200 dark:border-slate-800 text-slate-400 dark:text-slate-500' : 'border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800'}`} href={pageHref(posts.current_page - 1)} tabIndex={posts.current_page <= 1 ? -1 : undefined}>Previous</Link>
                            <Link aria-disabled={posts.current_page >= posts.last_page} className={`rounded-lg border px-3 py-2 font-medium ${posts.current_page >= posts.last_page ? 'pointer-events-none border-slate-200 dark:border-slate-800 text-slate-400 dark:text-slate-500' : 'border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800'}`} href={pageHref(posts.current_page + 1)} tabIndex={posts.current_page >= posts.last_page ? -1 : undefined}>Next</Link>
                        </nav>
                    </div>}
                </div> : <div className="rounded-2xl border border-dashed border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-5 py-14 text-center">
                    <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100">No updates yet</h2>
                    <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">{view === 'archived' ? 'Archived posts will appear here.' : view === 'moderated' ? 'Hidden posts will appear here for review.' : 'Team posts will appear here when they are shared.'}</p>
                </div>}
            </div>
        </AuthenticatedLayout>
    );
}
