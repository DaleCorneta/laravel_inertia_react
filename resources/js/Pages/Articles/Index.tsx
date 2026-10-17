import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import ConfirmDialog from '../../Components/ConfirmDialog';
import VisibleColumnSelector from '../../Components/Tables/VisibleColumnSelector';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';

type ArticleRow = { id: number; title: string; status: string; author: string; updated_at: string | null; archived_at: string | null };
type ArticlePage = { data: ArticleRow[]; current_page: number; last_page: number; total: number };
type ArticleAction = { id: number; title: string; action: 'publish' | 'unpublish' | 'archive' | 'unarchive' | 'trash' };

const exportColumns = [
    { value: 'title', label: 'Title' },
    { value: 'status', label: 'Status' },
    { value: 'author', label: 'Author' },
    { value: 'updated_at', label: 'Last updated' },
] as const;
type ExportColumn = typeof exportColumns[number]['value'];

function articleColumnValue(article: ArticleRow, column: ExportColumn) {
    if (column === 'title') return <span className="font-semibold text-slate-900 dark:text-slate-100">{article.title}</span>;
    if (column === 'status') {
        return <span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold capitalize ${article.status === 'published' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200'}`}>{article.status}</span>;
    }
    if (column === 'author') return article.author;

    return article.updated_at ? new Date(article.updated_at).toLocaleDateString() : '—';
}

export default function Index({ auth, articles, filters, can_create_articles, can_update_articles, can_preview_articles, can_publish_articles, can_unpublish_articles, can_archive_articles, can_delete_articles, can_view_article_trash, can_view_article_history }: {
    auth: { user: { display_name: string; email: string; can_manage_users: boolean } | null };
    articles: ArticlePage;
    filters: { search: string; view: 'active' | 'archived' };
    flash: { status?: string | null };
    can_create_articles: boolean;
    can_update_articles: boolean;
    can_preview_articles: boolean;
    can_publish_articles: boolean;
    can_unpublish_articles: boolean;
    can_archive_articles: boolean;
    can_delete_articles: boolean;
    can_view_article_trash: boolean;
    can_view_article_history: boolean;
}) {
    const [search, setSearch] = useState(filters.search);
    const [pendingAction, setPendingAction] = useState<ArticleAction | null>(null);
    const [selectedExportColumns, setSelectedExportColumns] = useState<ExportColumn[]>(exportColumns.map(({ value }) => value));
    const visibleColumns = exportColumns.filter(({ value }) => selectedExportColumns.includes(value));

    useEffect(() => {
        if (search.trim() === filters.search) return;
        const timeout = window.setTimeout(() => router.get('/articles', { search, view: filters.view, page: 1 }, { preserveState: true, preserveScroll: true, replace: true }), 350);
        return () => window.clearTimeout(timeout);
    }, [filters.search, search]);

    const pageHref = (page: number) => {
        const params = new URLSearchParams();
        if (filters.search) params.set('search', filters.search);
        if (filters.view === 'archived') params.set('view', 'archived');
        params.set('page', String(page));
        return `/articles?${params.toString()}`;
    };

    return <>
        <Head title="Articles" />
        <AuthenticatedLayout user={auth.user}>
            <section className="mx-auto min-w-0 w-full max-w-[1600px] py-4 sm:py-6">
                    <div className="flex flex-wrap items-end justify-between gap-4">
                    <div><p className="font-brand text-xs font-semibold uppercase tracking-[0.18em] text-brand">Publishing</p><h1 className="mt-2 font-display text-3xl font-semibold tracking-tight text-slate-950 dark:text-slate-100">{filters.view === 'archived' ? 'Archived articles' : 'Articles'}</h1><p className="mt-2 text-sm text-slate-600 dark:text-slate-300">Manage article drafts and published stories.</p></div>
                    <div className="flex flex-wrap gap-2">{can_view_article_trash && <Link className="inline-flex items-center justify-center rounded-lg border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800" href="/articles/trash">Trash</Link>}<Link className="inline-flex items-center justify-center rounded-lg border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800" href="/blog">View public blog</Link>{can_create_articles && <Link className="inline-flex items-center justify-center rounded-lg bg-brand px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand/90" href="/articles/create">Create article</Link>}</div>
                </div>
                <nav aria-label="Article views" className="mt-4 flex gap-2 border-b border-slate-200 dark:border-slate-800"><Link aria-current={filters.view === 'active' ? 'page' : undefined} className={`border-b-2 px-3 py-2 text-sm font-semibold ${filters.view === 'active' ? 'border-brand text-brand' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-800'}`} href="/articles">Active</Link><Link aria-current={filters.view === 'archived' ? 'page' : undefined} className={`border-b-2 px-3 py-2 text-sm font-semibold ${filters.view === 'archived' ? 'border-brand text-brand' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-800'}`} href="/articles?view=archived">Archived</Link></nav>
                <form action="/articles/export" className="mt-4 flex flex-col gap-4 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-sm sm:flex-row sm:items-end sm:justify-between" method="get">
                    <VisibleColumnSelector
                        className="min-w-0 grid grid-cols-2 gap-x-5 gap-y-2 sm:grid-cols-4"
                        columns={exportColumns}
                        legend="Choose columns shown in the table and exports"
                        onChange={setSelectedExportColumns}
                        selected={selectedExportColumns}
                    />
                    <div className="flex flex-wrap items-center gap-3">
                        <input name="search" type="hidden" value={search} />
                        <input name="view" type="hidden" value={filters.view} />
                        <span className="text-xs text-slate-500 dark:text-slate-400">Selected columns appear in the table and exports. Exports include all matching articles.</span>
                        <button className="inline-flex min-h-10 items-center justify-center rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white hover:bg-brand/90 disabled:cursor-not-allowed disabled:opacity-50" disabled={selectedExportColumns.length === 0} type="submit">Download CSV</button>
                        <button className="inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-4 py-2 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50" disabled={selectedExportColumns.length === 0} formAction="/articles/export/xlsx" type="submit">Download XLSX</button>
                        <button className="inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-4 py-2 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50" disabled={selectedExportColumns.length === 0} formAction="/articles/export/pdf" type="submit">Download PDF</button>
                    </div>
                </form>
                <div className="mt-5 overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 dark:border-slate-800 p-4 sm:px-5">
                        <div><h2 className="text-sm font-semibold text-slate-900 dark:text-slate-100">Article library</h2><p className="mt-1 text-xs text-slate-500 dark:text-slate-400">{articles.total.toLocaleString()} {articles.total === 1 ? 'article' : 'articles'} · {visibleColumns.length} {visibleColumns.length === 1 ? 'column' : 'columns'} visible</p></div>
                        <label className="w-full sm:max-w-xs"><span className="sr-only">Search articles</span><input className="block w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 px-3 py-2.5 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 focus:border-brand focus:bg-white dark:focus:bg-slate-800 focus:ring-brand" onChange={(event) => setSearch(event.target.value)} placeholder="Search by title…" type="search" value={search} /></label>
                    </div>
                    {articles.data.length > 0 ? <div aria-label="Article library table" className="overflow-x-auto focus-within:ring-2 focus-within:ring-brand" role="region" tabIndex={0}>
                        <table className="min-w-full divide-y divide-slate-200 dark:divide-slate-800 text-left text-sm">
                            <caption className="sr-only">Articles matching the current search and view</caption>
                            <thead className="bg-slate-50 dark:bg-slate-800 text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400"><tr>{visibleColumns.map(({ value, label }) => <th className="px-4 py-3 font-semibold sm:px-5" key={value} scope="col">{label}</th>)}{(can_view_article_history || can_preview_articles || can_update_articles || can_publish_articles || can_unpublish_articles || can_archive_articles || can_delete_articles || articles.data.some((article) => article.status === 'published')) && <th className="px-4 py-3 font-semibold sm:px-5" scope="col"><span className="sr-only">Actions</span></th>}</tr></thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">{articles.data.map((article) => <tr className="hover:bg-slate-50/70 dark:hover:bg-slate-800/60" key={article.id}>{visibleColumns.map(({ value }, index) => index === 0
                                ? <th className="max-w-sm px-4 py-4 sm:px-5" key={value} scope="row">{articleColumnValue(article, value)}</th>
                                : <td className={`px-4 py-4 sm:px-5 ${value === 'updated_at' ? 'whitespace-nowrap text-slate-500 dark:text-slate-400' : 'text-slate-600 dark:text-slate-300'}`} key={value}>{articleColumnValue(article, value)}</td>)}{(can_view_article_history || can_preview_articles || can_update_articles || can_publish_articles || can_unpublish_articles || can_archive_articles || can_delete_articles || article.status === 'published') && <td className="whitespace-nowrap px-4 py-4 text-right sm:px-5"><div className="flex justify-end gap-3">{can_view_article_history && <Link className="font-semibold text-slate-600 dark:text-slate-300 hover:text-brand hover:underline" href={`/articles/${article.id}/history`}>History</Link>}{article.status === 'published' && article.archived_at === null && <Link className="font-semibold text-slate-600 dark:text-slate-300 hover:text-brand hover:underline" href={`/blog/${article.id}`}>View</Link>}{can_preview_articles && article.status === 'draft' && article.archived_at === null && <Link className="font-semibold text-slate-600 dark:text-slate-300 hover:text-brand hover:underline" href={`/articles/${article.id}/preview`}>Preview</Link>}{can_update_articles && <Link className="font-semibold text-brand hover:underline" href={`/articles/${article.id}/edit`}>Edit</Link>}{can_publish_articles && article.status === 'draft' && article.archived_at === null && <button className="font-semibold text-emerald-700 hover:underline" onClick={() => setPendingAction({ id: article.id, title: article.title, action: 'publish' })} type="button">Publish</button>}{can_unpublish_articles && article.status === 'published' && article.archived_at === null && <button className="font-semibold text-amber-700 hover:underline" onClick={() => setPendingAction({ id: article.id, title: article.title, action: 'unpublish' })} type="button">Unpublish</button>}{can_archive_articles && article.archived_at === null && <button className="font-semibold text-slate-600 dark:text-slate-300 hover:underline" onClick={() => setPendingAction({ id: article.id, title: article.title, action: 'archive' })} type="button">Archive</button>}{can_archive_articles && article.archived_at !== null && <button className="font-semibold text-slate-600 dark:text-slate-300 hover:underline" onClick={() => setPendingAction({ id: article.id, title: article.title, action: 'unarchive' })} type="button">Unarchive</button>}{can_delete_articles && <button className="font-semibold text-red-700 dark:text-red-300 hover:underline" onClick={() => setPendingAction({ id: article.id, title: article.title, action: 'trash' })} type="button">Move to trash</button>}</div></td>}</tr>)}</tbody>
                        </table>
                    </div> : <div className="px-5 py-12 text-center"><p className="text-sm font-semibold text-slate-800 dark:text-slate-200">{filters.search ? 'No matching articles' : 'No articles yet'}</p><p className="mt-1 text-sm text-slate-500 dark:text-slate-400">{filters.search ? 'Try a different title.' : 'New articles will appear here.'}</p></div>}
                    {articles.last_page > 1 && <div className="flex items-center justify-between gap-3 border-t border-slate-100 dark:border-slate-800 px-4 py-3 text-sm sm:px-5"><span className="text-slate-500 dark:text-slate-400">Page {articles.current_page} of {articles.last_page}</span><div className="flex gap-2"><Link aria-disabled={articles.current_page <= 1} className={`rounded-lg border px-3 py-2 font-medium ${articles.current_page <= 1 ? 'pointer-events-none border-slate-200 dark:border-slate-800 text-slate-400 dark:text-slate-500' : 'border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800'}`} href={pageHref(articles.current_page - 1)}>Previous</Link><Link aria-disabled={articles.current_page >= articles.last_page} className={`rounded-lg border px-3 py-2 font-medium ${articles.current_page >= articles.last_page ? 'pointer-events-none border-slate-200 dark:border-slate-800 text-slate-400 dark:text-slate-500' : 'border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800'}`} href={pageHref(articles.current_page + 1)}>Next</Link></div></div>}
                </div>
                <ConfirmDialog
                    confirmLabel={pendingAction?.action === 'publish' ? 'Publish article' : pendingAction?.action === 'unpublish' ? 'Unpublish article' : pendingAction?.action === 'archive' ? 'Archive article' : pendingAction?.action === 'unarchive' ? 'Unarchive article' : 'Move to trash'}
                    description={pendingAction?.action === 'publish'
                        ? <>“{pendingAction.title}” will become visible on the public blog.</>
                        : pendingAction?.action === 'unpublish'
                            ? <>“{pendingAction.title}” will be removed from the public blog and returned to drafts.</>
                            : pendingAction?.action === 'archive'
                                ? <>“{pendingAction.title}” will leave the active library. If published, it will no longer be public.</>
                                : pendingAction?.action === 'unarchive'
                                    ? <>“{pendingAction.title}” will return to the active library. A published article will become public again.</>
                                    : <>“{pendingAction?.title}” will move to trash, where an authorized user can restore it.</>}
                    onCancel={() => setPendingAction(null)}
                    onConfirm={() => {
                        if (!pendingAction) return;
                        const url = `/articles/${pendingAction.id}`;
                        const options = { onFinish: () => setPendingAction(null) };
                        if (pendingAction.action === 'trash') router.delete(url, options);
                        else if (pendingAction.action === 'archive' || pendingAction.action === 'unarchive') router.patch(`${url}/${pendingAction.action}`, {}, options);
                        else router.post(`${url}/${pendingAction.action}`, {}, options);
                    }}
                    open={pendingAction !== null}
                    title={pendingAction?.action === 'publish' ? 'Publish this article?' : pendingAction?.action === 'unpublish' ? 'Unpublish this article?' : pendingAction?.action === 'archive' ? 'Archive this article?' : pendingAction?.action === 'unarchive' ? 'Unarchive this article?' : 'Move this article to trash?'}
                    tone={pendingAction?.action === 'trash' ? 'danger' : 'primary'}
                />
            </section>
        </AuthenticatedLayout>
    </>;
}
