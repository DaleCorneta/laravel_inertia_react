import { Head, Link } from '@inertiajs/react';
import type { JSONContent } from '@tiptap/core';
import ArticlePreviewContent from '../../Components/Articles/ArticlePreviewContent';
import AppLayout from '../../Layouts/AppLayout';

export default function Show({ article }: {
    article: { id: number; title: string; content: JSONContent; author: string; published_at: string; cover_image_url: string | null };
}) {
    return <>
        <Head title={article.title} />
        <AppLayout>
            <div className="mx-auto max-w-3xl">
                <Link className="text-sm font-semibold text-brand hover:underline" href="/blog">← All articles</Link>
                <article className="mt-5 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-sm sm:p-8 lg:p-10">
                    <header className="mb-8 border-b border-slate-100 dark:border-slate-800 pb-6"><p className="font-brand text-xs font-semibold uppercase tracking-[0.18em] text-brand">Company blog</p><h1 className="mt-3 font-display text-3xl font-semibold tracking-tight text-slate-950 dark:text-slate-100 sm:text-4xl">{article.title}</h1><p className="mt-3 text-sm text-slate-500 dark:text-slate-400">By {article.author} · <time dateTime={article.published_at}>{new Date(article.published_at).toLocaleDateString()}</time></p>{article.cover_image_url && <img alt={`${article.title} cover image`} className="mt-6 max-h-[32rem] w-full rounded-xl object-cover" src={article.cover_image_url} />}</header>
                    <ArticlePreviewContent articleId={article.id} content={article.content} publicView />
                </article>
            </div>
        </AppLayout>
    </>;
}
