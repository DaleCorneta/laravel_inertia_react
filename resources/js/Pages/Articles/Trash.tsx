import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import ConfirmDialog from '../../Components/ConfirmDialog';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';

type ArticleRow = { id: number; title: string; status: string; author: string; deleted_at: string | null; archived_at: string | null };
type ArticlePage = { data: ArticleRow[]; current_page: number; last_page: number; total: number };
type PendingAction = { article: ArticleRow; action: 'restore' | 'force-delete' };

export default function Trash({ auth, articles, can_restore_articles, can_force_delete_articles }: {
    auth: { user: { display_name: string; email: string; can_manage_users: boolean } | null };
    articles: ArticlePage;
    can_restore_articles: boolean;
    can_force_delete_articles: boolean;
}) {
    const [pendingAction, setPendingAction] = useState<PendingAction | null>(null);

    return <>
        <Head title="Article trash" />
        <AuthenticatedLayout user={auth.user}>
            <section className="mx-auto min-w-0 w-full max-w-[1400px] py-4 sm:py-6">
                <div className="mb-5 flex flex-wrap items-end justify-between gap-4">
                    <div><p className="font-brand text-xs font-semibold uppercase tracking-[0.18em] text-brand">Publishing</p><h1 className="mt-2 font-display text-3xl font-semibold tracking-tight text-slate-950 dark:text-slate-100">Article trash</h1><p className="mt-2 text-sm text-slate-600 dark:text-slate-300">Restore removed articles. Archived status is preserved when an article is restored.</p></div>
                    <Link className="inline-flex items-center justify-center rounded-lg border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800" href="/articles">Back to articles</Link>
                </div>
                <div className="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                    {articles.data.length > 0 ? <div aria-label="Article trash table" className="overflow-x-auto focus-within:ring-2 focus-within:ring-brand" role="region" tabIndex={0}><table className="min-w-full divide-y divide-slate-200 dark:divide-slate-800 text-left text-sm">
                        <caption className="sr-only">Removed articles</caption><thead className="bg-slate-50 dark:bg-slate-800 text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400"><tr><th className="px-4 py-3 font-semibold sm:px-5" scope="col">Title</th><th className="px-4 py-3 font-semibold sm:px-5" scope="col">Status</th><th className="px-4 py-3 font-semibold sm:px-5" scope="col">Author</th><th className="px-4 py-3 font-semibold sm:px-5" scope="col">Removed</th><th className="px-4 py-3 font-semibold sm:px-5" scope="col">Archive state</th>{(can_restore_articles || can_force_delete_articles) && <th className="px-4 py-3 font-semibold sm:px-5" scope="col"><span className="sr-only">Actions</span></th>}</tr></thead>
                        <tbody className="divide-y divide-slate-100 dark:divide-slate-800">{articles.data.map((article) => <tr className="hover:bg-slate-50/70 dark:hover:bg-slate-800/60" key={article.id}><th className="px-4 py-4 font-semibold text-slate-900 dark:text-slate-100 sm:px-5" scope="row">{article.title}</th><td className="px-4 py-4 capitalize text-slate-600 dark:text-slate-300 sm:px-5">{article.status}</td><td className="px-4 py-4 text-slate-600 dark:text-slate-300 sm:px-5">{article.author}</td><td className="whitespace-nowrap px-4 py-4 text-slate-500 dark:text-slate-400 sm:px-5">{article.deleted_at ? new Date(article.deleted_at).toLocaleDateString() : '—'}</td><td className="px-4 py-4 sm:px-5">{article.archived_at ? <span className="rounded-full bg-slate-100 dark:bg-slate-800 px-2.5 py-1 text-xs font-semibold text-slate-700 dark:text-slate-200">Archived</span> : <span className="text-slate-400 dark:text-slate-500">Active</span>}</td>{(can_restore_articles || can_force_delete_articles) && <td className="px-4 py-4 text-right sm:px-5"><div className="flex justify-end gap-3">{can_restore_articles && <button className="font-semibold text-brand hover:underline" onClick={() => setPendingAction({ article, action: 'restore' })} type="button">Restore</button>}{can_force_delete_articles && <button className="font-semibold text-red-700 dark:text-red-300 hover:underline" onClick={() => setPendingAction({ article, action: 'force-delete' })} type="button">Delete forever</button>}</div></td>}</tr>)}</tbody>
                    </table></div> : <div className="px-5 py-14 text-center"><p className="text-sm font-semibold text-slate-800 dark:text-slate-200">Trash is empty</p><p className="mt-1 text-sm text-slate-500 dark:text-slate-400">Removed articles will appear here.</p></div>}
                    {articles.last_page > 1 && <div className="flex items-center justify-between border-t border-slate-100 dark:border-slate-800 px-4 py-3 text-sm sm:px-5"><span className="text-slate-500 dark:text-slate-400">Page {articles.current_page} of {articles.last_page} · {articles.total} articles</span><div className="flex gap-2"><Link className="rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 font-medium text-slate-700 dark:text-slate-200" href={`/articles/trash?page=${Math.max(1, articles.current_page - 1)}`}>Previous</Link><Link className="rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 font-medium text-slate-700 dark:text-slate-200" href={`/articles/trash?page=${Math.min(articles.last_page, articles.current_page + 1)}`}>Next</Link></div></div>}
                </div>
                <ConfirmDialog
                    confirmLabel={pendingAction?.action === 'force-delete' ? 'Delete permanently' : 'Restore article'}
                    description={pendingAction?.action === 'force-delete'
                        ? <>“{pendingAction.article.title}” and its saved content will be permanently deleted. This cannot be undone.</>
                        : pendingAction?.article.status === 'published' && pendingAction.article.archived_at === null
                            ? <>“{pendingAction.article.title}” will return to the active library and become visible on the public blog again.</>
                            : <>“{pendingAction?.article.title}” will return to the {pendingAction?.article.archived_at ? 'archive' : 'active article library'}.</>}
                    onCancel={() => setPendingAction(null)}
                    onConfirm={() => {
                        if (!pendingAction) return;
                        if (pendingAction.action === 'force-delete') {
                            router.delete(`/articles/${pendingAction.article.id}/force-delete`, { onFinish: () => setPendingAction(null) });
                        } else {
                            router.post(`/articles/${pendingAction.article.id}/restore`, {}, { onFinish: () => setPendingAction(null) });
                        }
                    }}
                    open={pendingAction !== null}
                    title={pendingAction?.action === 'force-delete' ? 'Delete this article permanently?' : 'Restore this article?'}
                    tone={pendingAction?.action === 'force-delete' ? 'danger' : 'primary'}
                />
            </section>
        </AuthenticatedLayout>
    </>;
}
