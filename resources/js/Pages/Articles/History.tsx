import { Head, Link } from '@inertiajs/react';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';

type ActivityRow = { id: number; action: string; actor: string; occurred_at: string | null };
type ActivityPage = { data: ActivityRow[]; links: { url: string | null; label: string; active: boolean }[]; total: number };

export default function History({ auth, article, activities }: {
    auth: { user: { display_name: string; email: string; can_manage_users: boolean } | null };
    article: { id: number; title: string };
    activities: ActivityPage;
}) {
    return <>
        <Head title={`History: ${article.title}`} />
        <AuthenticatedLayout user={auth.user}>
            <section className="mx-auto min-w-0 w-full max-w-[1600px] py-4 sm:py-6">
                <Link className="text-sm font-semibold text-brand hover:underline" href="/articles">← Articles</Link>
                <div className="mt-4 flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="font-brand text-xs font-semibold uppercase tracking-[0.18em] text-brand">Publishing</p>
                        <h1 className="mt-2 font-display text-3xl font-semibold tracking-tight text-slate-950 dark:text-slate-100">Article history</h1>
                        <p className="mt-2 text-sm text-slate-600 dark:text-slate-300">{article.title}</p>
                    </div>
                    <span className="rounded-full border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-1.5 text-sm font-medium text-slate-600 dark:text-slate-300">{activities.total.toLocaleString()} {activities.total === 1 ? 'event' : 'events'}</span>
                </div>
                <section aria-label="Article history" className="mt-5 overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                    <div aria-label="Article history table" className="hidden overflow-x-auto focus-within:ring-2 focus-within:ring-brand sm:block" role="region" tabIndex={0}>
                        <table className="w-full text-left text-sm text-slate-600 dark:text-slate-300">
                            <caption className="sr-only">History for {article.title}</caption>
                            <thead className="bg-slate-50 dark:bg-slate-800 text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400"><tr><th className="px-4 py-3 font-semibold sm:px-5" scope="col">Action</th><th className="px-4 py-3 font-semibold sm:px-5" scope="col">Performed by</th><th className="px-4 py-3 font-semibold sm:px-5" scope="col">Date and time</th></tr></thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                {activities.data.map((activity) => <tr className="hover:bg-slate-50/70 dark:hover:bg-slate-800/60" key={activity.id}><th className="px-4 py-4 font-semibold text-slate-900 dark:text-slate-100 sm:px-5" scope="row">{activity.action}</th><td className="px-4 py-4 sm:px-5">{activity.actor}</td><td className="whitespace-nowrap px-4 py-4 sm:px-5">{activity.occurred_at ? new Date(activity.occurred_at).toLocaleString() : '—'}</td></tr>)}
                                {activities.data.length === 0 && <tr><td className="px-5 py-12 text-center text-slate-500 dark:text-slate-400" colSpan={3}>No history recorded for this article.</td></tr>}
                            </tbody>
                        </table>
                    </div>
                    <ul className="divide-y divide-slate-100 dark:divide-slate-800 sm:hidden">
                        {activities.data.map((activity) => <li className="grid gap-2 p-4 text-sm" key={activity.id}><p className="font-semibold text-slate-900 dark:text-slate-100">{activity.action}</p><p className="text-slate-600 dark:text-slate-300">{activity.actor}</p><time className="text-xs text-slate-500 dark:text-slate-400">{activity.occurred_at ? new Date(activity.occurred_at).toLocaleString() : '—'}</time></li>)}
                        {activities.data.length === 0 && <li className="px-5 py-12 text-center text-sm text-slate-500 dark:text-slate-400">No history recorded for this article.</li>}
                    </ul>
                    {activities.links.length > 3 && <nav aria-label="History pages" className="flex flex-wrap justify-end gap-2 border-t border-slate-100 dark:border-slate-800 px-4 py-3 sm:px-5">{activities.links.map((link, index) => <Link aria-current={link.active ? 'page' : undefined} aria-disabled={!link.url} className={`rounded-lg border px-3 py-2 text-sm ${link.active ? 'border-brand bg-brand text-white' : link.url ? 'border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800' : 'pointer-events-none border-slate-200 dark:border-slate-800 text-slate-400 dark:text-slate-500'}`} href={link.url ?? '#'} key={`${link.label}-${index}`} preserveScroll>{link.label.replace('&laquo; Previous', 'Previous').replace('Next &raquo;', 'Next')}</Link>)}</nav>}
                </section>
            </section>
        </AuthenticatedLayout>
    </>;
}
