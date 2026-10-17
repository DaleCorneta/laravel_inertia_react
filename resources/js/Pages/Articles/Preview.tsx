import { Head, Link } from '@inertiajs/react';
import type { JSONContent } from '@tiptap/core';
import ArticlePreviewContent from '../../Components/Articles/ArticlePreviewContent';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';

export default function Preview({ auth, article, can_update_articles }: {
    auth: { user: { display_name: string; email: string; can_manage_users: boolean } | null };
    article: { id: number; title: string; content: JSONContent; author: string; updated_at: string | null };
    can_update_articles: boolean;
}) {
    return <>
        <Head title={`Preview: ${article.title}`} />
        <AuthenticatedLayout user={auth.user}>
            <section className="mx-auto min-w-0 w-full max-w-[1100px] py-4 sm:py-6">
                <div className="mb-5 flex flex-wrap items-end justify-between gap-4">
                    <div><p className="font-brand text-xs font-semibold uppercase tracking-[0.18em] text-brand">Publishing · Draft preview</p><h1 className="mt-2 font-display text-3xl font-semibold tracking-tight text-slate-950 dark:text-slate-100">{article.title}</h1><p className="mt-2 text-sm text-slate-600 dark:text-slate-300">By {article.author}{article.updated_at ? ` · Updated ${new Date(article.updated_at).toLocaleDateString()}` : ''}</p></div>
                    <div className="flex gap-2"><Link className="inline-flex items-center justify-center rounded-lg border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800" href="/articles">Back to articles</Link>{can_update_articles && <Link className="inline-flex items-center justify-center rounded-lg bg-brand px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand/90" href={`/articles/${article.id}/edit`}>Edit draft</Link>}</div>
                </div>
                <article className="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-sm sm:p-8 lg:p-10">
                    <div className="mb-8 border-b border-slate-100 dark:border-slate-800 pb-4"><span className="inline-flex rounded-full bg-amber-50 dark:bg-amber-950 px-2.5 py-1 text-xs font-semibold text-amber-800 dark:text-amber-300">Draft · private preview</span></div>
                    <ArticlePreviewContent articleId={article.id} content={article.content} />
                </article>
            </section>
        </AuthenticatedLayout>
    </>;
}
