import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import type { JSONContent } from '@tiptap/core';
import Button from '../../Components/Auth/Button';
import FormField from '../../Components/Auth/FormField';
import ArticleEditor from '../../Components/Articles/ArticleEditor';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';

type ArticleFormData = { title: string; content: JSONContent; cover_image: File | null };

export default function Create({ auth }: { auth: { user: { display_name: string; email: string; can_manage_users: boolean } | null } }) {
    const form = useForm<ArticleFormData>({ title: '', content: { type: 'doc', content: [{ type: 'paragraph' }] }, cover_image: null });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/articles', { forceFormData: true });
    };

    return <>
        <Head title="Create article" />
        <AuthenticatedLayout user={auth.user}>
            <section className="mx-auto min-w-0 w-full max-w-[1100px] py-4 sm:py-6">
                <div><p className="font-brand text-xs font-semibold uppercase tracking-[0.18em] text-brand">Publishing</p><h1 className="mt-2 font-display text-3xl font-semibold tracking-tight text-slate-950 dark:text-slate-100">Create an article</h1><p className="mt-2 text-sm text-slate-600 dark:text-slate-300">Start a new draft for your workspace.</p></div>
                <form className="mt-5 space-y-5 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-sm sm:p-6" onSubmit={submit}>
                    <FormField autoComplete="off" id="title" label="Article title" maxLength={200} onChange={(event) => form.setData('title', event.target.value)} required value={form.data.title} error={form.errors.title} />
                    <div>
                        <label className="mb-2 block text-sm font-medium text-slate-900 dark:text-slate-100" htmlFor="cover_image">Cover image <span className="font-normal text-slate-500 dark:text-slate-400">(optional, JPEG or PNG, up to 4 MB)</span></label>
                        <input accept="image/jpeg,image/png" className="block w-full cursor-pointer rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-sm text-slate-700 dark:text-slate-200 file:mr-4 file:border-0 file:bg-slate-100 dark:file:bg-slate-700 file:px-4 file:py-2.5 file:font-semibold file:text-slate-700 dark:file:text-slate-200" id="cover_image" onChange={(event) => form.setData('cover_image', event.target.files?.[0] ?? null)} type="file" />
                        {form.errors.cover_image && <p className="mt-2 text-sm text-red-700 dark:text-red-300" role="alert">{form.errors.cover_image}</p>}
                    </div>
                    <div>
                        <p className="mb-2 text-sm font-medium text-slate-900 dark:text-slate-100">Article content</p>
                        <ArticleEditor error={form.errors.content} onChange={(content) => form.setData('content', content)} value={form.data.content} />
                        <p className="mt-2 text-xs text-slate-500 dark:text-slate-400" id="content-help">Use headings, lists, links, and approved typography. Your article will be saved as a draft.</p>
                        {form.errors.content && <p className="mt-2 text-sm text-red-700 dark:text-red-300" id="content-error" role="alert">{form.errors.content}</p>}
                    </div>
                    <div className="flex flex-col-reverse gap-3 border-t border-slate-100 dark:border-slate-800 pt-5 sm:flex-row sm:justify-end"><Link className="inline-flex items-center justify-center rounded-lg border border-slate-300 dark:border-slate-700 px-5 py-3 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800" href="/articles">Cancel</Link><Button disabled={form.processing} type="submit">{form.processing ? 'Saving…' : 'Save draft'}</Button></div>
                </form>
            </section>
        </AuthenticatedLayout>
    </>;
}
