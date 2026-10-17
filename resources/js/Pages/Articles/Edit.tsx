import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import type { JSONContent } from '@tiptap/core';
import Button from '../../Components/Auth/Button';
import FormField from '../../Components/Auth/FormField';
import ArticleEditor from '../../Components/Articles/ArticleEditor';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';

type ArticleFormData = { title: string; content: JSONContent; cover_image: File | null; remove_cover_image: boolean };

export default function Edit({ auth, article }: {
    auth: { user: { display_name: string; email: string; can_manage_users: boolean } | null };
    article: { id: number; title: string; content: JSONContent; cover_image_url: string | null };
}) {
    const form = useForm<ArticleFormData>({ title: article.title, content: article.content, cover_image: null, remove_cover_image: false });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({ ...data, _method: 'put' }));
        form.post(`/articles/${article.id}`, { forceFormData: true });
    };

    return <>
        <Head title="Edit article" />
        <AuthenticatedLayout user={auth.user}>
            <section className="mx-auto min-w-0 w-full max-w-[1100px] py-4 sm:py-6">
                <div><p className="font-brand text-xs font-semibold uppercase tracking-[0.18em] text-brand">Publishing</p><h1 className="mt-2 font-display text-3xl font-semibold tracking-tight text-slate-950 dark:text-slate-100">Edit article</h1><p className="mt-2 text-sm text-slate-600 dark:text-slate-300">Update this article draft.</p></div>
                <form className="mt-5 space-y-5 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-sm sm:p-6" onSubmit={submit}>
                    <FormField autoComplete="off" id="title" label="Article title" maxLength={200} onChange={(event) => form.setData('title', event.target.value)} required value={form.data.title} error={form.errors.title} />
                    <div>
                        <label className="mb-2 block text-sm font-medium text-slate-900 dark:text-slate-100" htmlFor="cover_image">Cover image <span className="font-normal text-slate-500 dark:text-slate-400">(optional, JPEG or PNG, up to 4 MB)</span></label>
                        {article.cover_image_url && !form.data.remove_cover_image && <img alt="Current article cover" className="mb-3 max-h-56 w-full rounded-xl border border-slate-200 dark:border-slate-800 object-cover sm:max-w-md" src={article.cover_image_url} />}
                        <input accept="image/jpeg,image/png" className="block w-full cursor-pointer rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-sm text-slate-700 dark:text-slate-200 file:mr-4 file:border-0 file:bg-slate-100 dark:file:bg-slate-700 file:px-4 file:py-2.5 file:font-semibold file:text-slate-700 dark:file:text-slate-200" id="cover_image" onChange={(event) => { form.setData('cover_image', event.target.files?.[0] ?? null); if (event.target.files?.[0]) form.setData('remove_cover_image', false); }} type="file" />
                        {article.cover_image_url && <label className="mt-3 flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300"><input checked={form.data.remove_cover_image} className="rounded border-slate-300 dark:border-slate-700 text-brand focus:ring-brand" onChange={(event) => form.setData('remove_cover_image', event.target.checked)} type="checkbox" />Remove current cover image</label>}
                        {form.errors.cover_image && <p className="mt-2 text-sm text-red-700 dark:text-red-300" role="alert">{form.errors.cover_image}</p>}
                    </div>
                    <div>
                        <p className="mb-2 text-sm font-medium text-slate-900 dark:text-slate-100">Article content</p>
                        <ArticleEditor articleId={article.id} error={form.errors.content} onChange={(content) => form.setData('content', content)} value={form.data.content} />
                        <p className="mt-2 text-xs text-slate-500 dark:text-slate-400" id="content-help">Use headings, lists, links, and approved typography. The article remains a draft.</p>
                        {form.errors.content && <p className="mt-2 text-sm text-red-700 dark:text-red-300" id="content-error" role="alert">{form.errors.content}</p>}
                    </div>
                    <div className="flex flex-col-reverse gap-3 border-t border-slate-100 dark:border-slate-800 pt-5 sm:flex-row sm:justify-end"><Link className="inline-flex items-center justify-center rounded-lg border border-slate-300 dark:border-slate-700 px-5 py-3 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800" href="/articles">Cancel</Link><Button disabled={form.processing} type="submit">{form.processing ? 'Saving…' : 'Save changes'}</Button></div>
                </form>
            </section>
        </AuthenticatedLayout>
    </>;
}
