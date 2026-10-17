import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import ConfirmDialog from '../../Components/ConfirmDialog';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';

type Comment = { id: number; body: string; author: string; created_at: string | null; can_update: boolean; can_delete: boolean; can_reply: boolean; can_moderate: boolean; moderated: boolean; replies: Comment[] };
type CommentsPage = { data: Comment[]; current_page: number; last_page: number; total: number };

function CommentCard({ postId, comment, depth = 0 }: { postId: number; comment: Comment; depth?: number }) {
    const [editing, setEditing] = useState(false);
    const [confirmDelete, setConfirmDelete] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const [replying, setReplying] = useState(false);
    const form = useForm({ body: comment.body });
    const replyForm = useForm({ body: '' });

    return <div className={depth ? 'border-l border-slate-200 dark:border-slate-800 pl-4 sm:pl-6' : ''}>
        <article className="flex gap-3 py-4">
            <span aria-hidden="true" className="grid size-9 shrink-0 place-items-center rounded-full bg-slate-100 dark:bg-slate-800 text-xs font-bold text-slate-600 dark:text-slate-300">{comment.author.slice(0, 1).toUpperCase()}</span>
            <div className="min-w-0 flex-1">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                    <span className="text-sm font-semibold text-slate-900 dark:text-slate-100">{comment.author}</span>
                    <time className="text-xs text-slate-500 dark:text-slate-400" dateTime={comment.created_at ?? undefined}>{comment.created_at ? new Date(comment.created_at).toLocaleString() : 'Date unavailable'}</time>
                </div>
                {!editing && (comment.can_update || comment.can_delete || comment.can_reply || comment.can_moderate) && <div className="flex flex-wrap gap-2">
                    {comment.can_reply && <button className="text-xs font-semibold text-brand hover:underline" onClick={() => setReplying((value) => !value)} type="button">{replying ? 'Cancel reply' : 'Reply'}</button>}
                    {comment.can_update && <button className="text-xs font-semibold text-slate-600 dark:text-slate-300 hover:text-brand" onClick={() => { form.setData('body', comment.body); form.clearErrors(); setEditing(true); }} type="button">Edit</button>}
                    {comment.can_delete && <button className="text-xs font-semibold text-red-700 dark:text-red-300 hover:underline" onClick={() => setConfirmDelete(true)} type="button">Delete</button>}
                    {comment.can_moderate && <button className="text-xs font-semibold text-amber-800 dark:text-amber-300 hover:underline" onClick={() => router.patch(`/community/${postId}/comments/${comment.id}/moderation`, { action: comment.moderated ? 'restore' : 'hide' }, { preserveScroll: true })} type="button">{comment.moderated ? 'Restore' : 'Hide'}</button>}
                </div>}
            </div>
            {comment.moderated && <p className="mt-2 inline-flex rounded-full bg-amber-50 dark:bg-amber-950 px-2.5 py-1 text-xs font-semibold text-amber-800 dark:text-amber-300">Hidden by moderation</p>}
            {editing ? <form className="mt-2" onSubmit={(event) => {
                event.preventDefault();
                form.put(`/community/${postId}/comments/${comment.id}`, { onSuccess: () => setEditing(false) });
            }}>
                <label className="sr-only" htmlFor={`comment-${comment.id}`}>Edit comment</label>
                <textarea aria-describedby={form.errors.body ? `comment-${comment.id}-error` : undefined} aria-invalid={Boolean(form.errors.body)} className="block min-h-20 w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 p-3 text-sm text-slate-900 dark:text-slate-100 focus:border-brand focus:ring-brand" id={`comment-${comment.id}`} maxLength={2000} onChange={(event) => form.setData('body', event.target.value)} value={form.data.body} />
                {form.errors.body && <p className="mt-1 text-sm text-red-700 dark:text-red-300" id={`comment-${comment.id}-error`} role="alert">{form.errors.body}</p>}
                <div className="mt-2 flex justify-end gap-2">
                    <button className="rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800" onClick={() => { form.setData('body', comment.body); form.clearErrors(); setEditing(false); }} type="button">Cancel</button>
                    <button className="rounded-lg bg-brand px-3 py-2 text-sm font-semibold text-white hover:brightness-95 disabled:opacity-60" disabled={form.processing || !form.data.body.trim()} type="submit">{form.processing ? 'Saving…' : 'Save'}</button>
                </div>
            </form> : <p className="mt-1 whitespace-pre-wrap break-words text-sm leading-6 text-slate-700 dark:text-slate-200">{comment.body}</p>}
            {replying && <form className="mt-3" onSubmit={(event) => {
                event.preventDefault();
                replyForm.post(`/community/${postId}/comments/${comment.id}/replies`, { onSuccess: () => { replyForm.reset(); setReplying(false); } });
            }}>
                <label className="sr-only" htmlFor={`reply-${comment.id}`}>Reply to {comment.author}</label>
                <textarea aria-describedby={replyForm.errors.body ? `reply-${comment.id}-error` : undefined} aria-invalid={Boolean(replyForm.errors.body)} className="block min-h-20 w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 p-3 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:border-brand focus:ring-brand" id={`reply-${comment.id}`} maxLength={2000} onChange={(event) => replyForm.setData('body', event.target.value)} placeholder="Write a reply…" value={replyForm.data.body} />
                {replyForm.errors.body && <p className="mt-1 text-sm text-red-700 dark:text-red-300" id={`reply-${comment.id}-error`} role="alert">{replyForm.errors.body}</p>}
                <div className="mt-2 flex justify-end">
                    <button className="rounded-lg bg-brand px-3 py-2 text-sm font-semibold text-white hover:brightness-95 disabled:opacity-60" disabled={replyForm.processing || !replyForm.data.body.trim()} type="submit">{replyForm.processing ? 'Sending…' : 'Send reply'}</button>
                </div>
            </form>}
            <ConfirmDialog
                cancelLabel="Keep comment"
                confirmLabel={deleting ? 'Deleting…' : 'Delete comment'}
                description="This permanently deletes the comment."
                onCancel={() => setConfirmDelete(false)}
                onConfirm={() => router.delete(`/community/${postId}/comments/${comment.id}`, { onStart: () => setDeleting(true), onFinish: () => setDeleting(false), onSuccess: () => setConfirmDelete(false) })}
                open={confirmDelete}
                title="Delete this comment?"
            />
            </div>
        </article>
        {comment.replies.length > 0 && <div className="ml-3 divide-y divide-slate-100 dark:divide-slate-800 sm:ml-5">{comment.replies.map((reply) => <CommentCard comment={reply} depth={1} key={reply.id} postId={postId} />)}</div>}
    </div>;
}

export default function Comments({ auth, post, comments, can_comment }: {
    auth: { user: { display_name: string; email: string; can_manage_users: boolean } | null };
    post: { id: number; body: string; author: string; archived: boolean; moderated: boolean };
    comments: CommentsPage;
    can_comment: boolean;
}) {
    const form = useForm({ body: '' });
    const pageHref = (page: number) => `/community/${post.id}/comments?page=${page}`;

    return <>
        <Head title="Post discussion" />
        <AuthenticatedLayout user={auth.user}>
            <section className="mx-auto w-full max-w-4xl space-y-5">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p className="text-sm font-semibold text-brand">Community</p>
                        <h1 className="mt-1 font-brand text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">Post discussion</h1>
                        <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">Comments are visible to members who can view the feed.</p>
                    </div>
                    <Link className="rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800" href="/community">Back to feed</Link>
                </div>

                <article className="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-sm sm:p-6">
                    <div className="flex items-center gap-3">
                        <span aria-hidden="true" className="grid size-10 place-items-center rounded-full bg-brand/10 text-sm font-bold text-brand">{post.author.slice(0, 1).toUpperCase()}</span>
                        <div><p className="text-sm font-semibold text-slate-900 dark:text-slate-100">{post.author}</p><p className="text-xs text-slate-500 dark:text-slate-400">Original post</p></div>
                    </div>
                    <p className="mt-4 whitespace-pre-wrap break-words text-sm leading-7 text-slate-700 dark:text-slate-200">{post.body}</p>
                    {post.archived && <p className="mt-4 rounded-lg bg-amber-50 dark:bg-amber-950 px-3 py-2 text-sm text-amber-800 dark:text-amber-300">This post is archived. New comments are disabled.</p>}
                    {post.moderated && <p className="mt-4 rounded-lg bg-amber-50 dark:bg-amber-950 px-3 py-2 text-sm text-amber-800 dark:text-amber-300">This post is hidden from the feed and visible here only to moderators.</p>}
                </article>

                {can_comment && <form className="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-sm sm:p-5" onSubmit={(event) => {
                    event.preventDefault();
                    form.post(`/community/${post.id}/comments`, { onSuccess: () => form.reset() });
                }}>
                    <label className="mb-2 block text-sm font-semibold text-slate-900 dark:text-slate-100" htmlFor="new-comment">Add a comment</label>
                    <textarea aria-describedby={`new-comment-hint${form.errors.body ? ' new-comment-error' : ''}`} aria-invalid={Boolean(form.errors.body)} className="block min-h-24 w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 p-3 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:border-brand focus:ring-brand" id="new-comment" maxLength={2000} onChange={(event) => form.setData('body', event.target.value)} placeholder="Write a comment…" value={form.data.body} />
                    <div className="mt-2 flex flex-wrap items-center justify-between gap-3">
                        <p className="text-xs text-slate-500 dark:text-slate-400" id="new-comment-hint">Up to 2,000 characters.</p>
                        <button className="rounded-lg bg-brand px-4 py-2.5 text-sm font-semibold text-white hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-60" disabled={form.processing || !form.data.body.trim()} type="submit">{form.processing ? 'Posting…' : 'Post comment'}</button>
                    </div>
                    {form.errors.body && <p className="mt-2 text-sm text-red-700 dark:text-red-300" id="new-comment-error" role="alert">{form.errors.body}</p>}
                </form>}

                <div className="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-4 shadow-sm sm:px-5">
                    <div className="flex items-center justify-between gap-3 border-b border-slate-100 dark:border-slate-800 py-4">
                        <h2 className="text-sm font-semibold text-slate-900 dark:text-slate-100">Comments</h2>
                        <span className="text-xs text-slate-500 dark:text-slate-400">{comments.total.toLocaleString()} total</span>
                    </div>
                    {comments.data.length ? <div className="divide-y divide-slate-100 dark:divide-slate-800">{comments.data.map((comment) => <CommentCard comment={comment} key={comment.id} postId={post.id} />)}</div> : <p className="py-10 text-center text-sm text-slate-500 dark:text-slate-400">No comments yet.</p>}
                    {comments.last_page > 1 && <div className="flex items-center justify-between gap-3 border-t border-slate-100 dark:border-slate-800 py-3 text-sm">
                        <span className="text-slate-500 dark:text-slate-400">Page {comments.current_page} of {comments.last_page}</span>
                        <nav aria-label="Comment pages" className="flex flex-wrap gap-2">
                            <Link aria-disabled={comments.current_page <= 1} className={`rounded-lg border px-3 py-2 font-medium ${comments.current_page <= 1 ? 'pointer-events-none border-slate-200 dark:border-slate-800 text-slate-400 dark:text-slate-500' : 'border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800'}`} href={pageHref(comments.current_page - 1)} tabIndex={comments.current_page <= 1 ? -1 : undefined}>Previous</Link>
                            <Link aria-disabled={comments.current_page >= comments.last_page} className={`rounded-lg border px-3 py-2 font-medium ${comments.current_page >= comments.last_page ? 'pointer-events-none border-slate-200 dark:border-slate-800 text-slate-400 dark:text-slate-500' : 'border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800'}`} href={pageHref(comments.current_page + 1)} tabIndex={comments.current_page >= comments.last_page ? -1 : undefined}>Next</Link>
                        </nav>
                    </div>}
                </div>
            </section>
        </AuthenticatedLayout>
    </>;
}
