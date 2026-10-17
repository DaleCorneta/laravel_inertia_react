import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import ConfirmDialog from '../../Components/ConfirmDialog';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';

type Post = { id: number; body: string; author: string; deleted_at: string | null; archived_at: string | null };
type PostsPage = { data: Post[]; current_page: number; last_page: number; total: number };

export default function Trash({ auth, posts, can_restore_posts, can_force_delete_posts }: {
    auth: { user: { display_name: string; email: string; can_manage_users: boolean } | null };
    posts: PostsPage;
    can_restore_posts: boolean;
    can_force_delete_posts: boolean;
}) {
    const [pendingDelete, setPendingDelete] = useState<number | null>(null);
    const [deleting, setDeleting] = useState(false);
    const pageHref = (page: number) => `/community/trash?page=${page}`;

    return <>
        <Head title="Community trash" />
        <AuthenticatedLayout user={auth.user}>
            <section className="mx-auto w-full max-w-5xl space-y-5">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p className="text-sm font-semibold text-brand">Community</p>
                        <h1 className="mt-1 font-brand text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">Post trash</h1>
                        <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">Restore removed posts. Their archived status is kept when restored.</p>
                    </div>
                    <Link className="rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800" href="/community">Back to feed</Link>
                </div>

                <div className="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                    {posts.data.length ? <div className="divide-y divide-slate-100 dark:divide-slate-800">
                        {posts.data.map((post) => <article className="flex flex-wrap items-start justify-between gap-4 p-4 sm:p-5" key={post.id}>
                            <div className="min-w-0 flex-1">
                                <p className="whitespace-pre-wrap break-words text-sm leading-6 text-slate-800 dark:text-slate-200">{post.body}</p>
                                <div className="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500 dark:text-slate-400">
                                    <span>By {post.author}</span>
                                    <span>Removed {post.deleted_at ? new Date(post.deleted_at).toLocaleString() : 'date unavailable'}</span>
                                    {post.archived_at && <span className="rounded-full bg-slate-100 dark:bg-slate-800 px-2 py-1 font-semibold text-slate-600 dark:text-slate-300">Archived before removal</span>}
                                </div>
                            </div>
                            {(can_restore_posts || can_force_delete_posts) && <div className="flex shrink-0 gap-2">
                                {can_restore_posts && <button className="rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 focus:outline-none focus:ring-4 focus:ring-slate-100 dark:focus:ring-slate-700" onClick={() => router.post(`/community/${post.id}/restore`)} type="button">Restore</button>}
                                {can_force_delete_posts && <button className="rounded-lg px-3 py-2 text-sm font-semibold text-red-700 dark:text-red-300 hover:bg-red-50 dark:hover:bg-red-950 focus:outline-none focus:ring-4 focus:ring-red-100 dark:focus:ring-red-900" onClick={() => setPendingDelete(post.id)} type="button">Delete permanently</button>}
                            </div>}
                        </article>)}
                    </div> : <div className="px-5 py-14 text-center">
                        <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100">Trash is empty</h2>
                        <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">Removed posts will appear here.</p>
                    </div>}
                    {posts.last_page > 1 && <div className="flex items-center justify-between gap-3 border-t border-slate-100 dark:border-slate-800 px-4 py-3 text-sm sm:px-5">
                        <span className="text-slate-500 dark:text-slate-400">Page {posts.current_page} of {posts.last_page}</span>
                        <nav aria-label="Community trash pages" className="flex flex-wrap gap-2">
                            <Link aria-disabled={posts.current_page <= 1} className={`rounded-lg border px-3 py-2 font-medium ${posts.current_page <= 1 ? 'pointer-events-none border-slate-200 dark:border-slate-800 text-slate-400 dark:text-slate-500' : 'border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800'}`} href={pageHref(posts.current_page - 1)} tabIndex={posts.current_page <= 1 ? -1 : undefined}>Previous</Link>
                            <Link aria-disabled={posts.current_page >= posts.last_page} className={`rounded-lg border px-3 py-2 font-medium ${posts.current_page >= posts.last_page ? 'pointer-events-none border-slate-200 dark:border-slate-800 text-slate-400 dark:text-slate-500' : 'border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800'}`} href={pageHref(posts.current_page + 1)} tabIndex={posts.current_page >= posts.last_page ? -1 : undefined}>Next</Link>
                        </nav>
                    </div>}
                </div>
                <ConfirmDialog
                    cancelLabel="Keep in trash"
                    confirmLabel={deleting ? 'Deleting…' : 'Delete permanently'}
                    description="This permanently deletes the post and cannot be undone."
                    onCancel={() => setPendingDelete(null)}
                    onConfirm={() => pendingDelete !== null && router.delete(`/community/${pendingDelete}/force-delete`, { onStart: () => setDeleting(true), onFinish: () => { setDeleting(false); setPendingDelete(null); } })}
                    open={pendingDelete !== null}
                    title="Permanently delete this post?"
                />
            </section>
        </AuthenticatedLayout>
    </>;
}
