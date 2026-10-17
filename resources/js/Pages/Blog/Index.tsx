import { Head, Link } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';

type Article = { id: number; title: string; author: string; published_at: string; cover_image_url: string | null };
type Page = { data: Article[]; current_page: number; last_page: number };

export default function Index({ articles }: { articles: Page }) {
    return <>
        <Head title="Blog" />
        <AppLayout>
            <header className="mb-8 flex flex-wrap items-end justify-between gap-4 border-b border-slate-200 dark:border-slate-800 pb-5">
                <div><p className="font-brand text-xs font-semibold uppercase tracking-[0.18em] text-brand">Company blog</p><h1 className="mt-2 font-display text-3xl font-semibold tracking-tight text-slate-950 dark:text-slate-100">Articles</h1><p className="mt-2 text-sm text-slate-600 dark:text-slate-300">Updates and stories from our team.</p></div>
                <Link className="text-sm font-semibold text-brand hover:underline" href="/">Home</Link>
            </header>
            {articles.data.length > 0 ? <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">{articles.data.map((article) => <article className="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm" key={article.id}>
                {article.cover_image_url && <img alt={`${article.title} cover image`} className="aspect-[16/9] w-full object-cover" loading="lazy" src={article.cover_image_url} />}
                <div className="p-5">
                <p className="text-xs text-slate-500 dark:text-slate-400"><time dateTime={article.published_at}>{new Date(article.published_at).toLocaleDateString()}</time> · {article.author}</p>
                <h2 className="mt-3 font-display text-xl font-semibold text-slate-950 dark:text-slate-100"><Link className="hover:text-brand" href={`/blog/${article.id}`}>{article.title}</Link></h2>
                <Link className="mt-5 inline-flex text-sm font-semibold text-brand hover:underline" href={`/blog/${article.id}`}>Read article</Link>
                </div>
            </article>)}</div> : <div className="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-5 py-16 text-center"><h2 className="font-display text-lg font-semibold text-slate-900 dark:text-slate-100">No articles published yet</h2><p className="mt-1 text-sm text-slate-500 dark:text-slate-400">Please check back later.</p></div>}
            {articles.last_page > 1 && <nav aria-label="Blog pages" className="mt-6 flex flex-wrap items-center justify-between gap-2"><Link aria-disabled={articles.current_page <= 1} className={`rounded-lg border px-3 py-2 text-sm font-medium ${articles.current_page <= 1 ? 'pointer-events-none border-slate-200 dark:border-slate-800 text-slate-400 dark:text-slate-500' : 'border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-white dark:hover:bg-slate-800'}`} href={`/blog?page=${Math.max(1, articles.current_page - 1)}`} tabIndex={articles.current_page <= 1 ? -1 : undefined}>Previous</Link><span aria-current="page" className="text-sm text-slate-500 dark:text-slate-400">Page {articles.current_page} of {articles.last_page}</span><Link aria-disabled={articles.current_page >= articles.last_page} className={`rounded-lg border px-3 py-2 text-sm font-medium ${articles.current_page >= articles.last_page ? 'pointer-events-none border-slate-200 dark:border-slate-800 text-slate-400 dark:text-slate-500' : 'border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-white dark:hover:bg-slate-800'}`} href={`/blog?page=${Math.min(articles.last_page, articles.current_page + 1)}`} tabIndex={articles.current_page >= articles.last_page ? -1 : undefined}>Next</Link></nav>}
        </AppLayout>
    </>;
}
