import { Link, router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import Button from '../Auth/Button';
import ConfirmDialog from '../ConfirmDialog';
import FormField from '../Auth/FormField';

export type ContactFields = { prefix: string; first_name: string; middle_name: string; last_name: string; suffix: string; organization: string; title: string; email: string; phone: string; notes: string };
export type ContactRecord = { id: number } & { [Field in keyof ContactFields]: string | null };
export type ContactAttachment = { id: number; original_name: string; size: number };
type ContactFormData = ContactFields & { attachments: File[] };

const emptyContact: ContactFields = { prefix: '', first_name: '', middle_name: '', last_name: '', suffix: '', organization: '', title: '', email: '', phone: '', notes: '' };

export default function ContactForm({ contact, attachments = [] }: { contact?: ContactRecord; attachments?: ContactAttachment[] }) {
    const initialData = contact ? Object.fromEntries(Object.keys(emptyContact).map((key) => [key, contact[key as keyof ContactFields] ?? ''])) as ContactFields : emptyContact;
    const form = useForm<ContactFormData>({ ...initialData, attachments: [] });
    const [attachmentToDelete, setAttachmentToDelete] = useState<ContactAttachment | null>(null);
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({ ...data, ...(contact ? { _method: 'put' } : {}) }));
        form.post(contact ? `/contacts/${contact.id}` : '/contacts', { forceFormData: true, onSuccess: () => form.reset('attachments') });
    };
    const attachmentErrors = Object.entries(form.errors).filter(([key]) => key === 'attachments' || key.startsWith('attachments.')).map(([, message]) => message);

    return <>
        <form className="mt-6 space-y-6 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-sm sm:p-6" onSubmit={submit}>
            <div><h2 className="text-base font-semibold text-slate-900 dark:text-slate-100">Personal details</h2><div className="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <FormField autoComplete="honorific-prefix" id="prefix" label="Prefix" maxLength={20} onChange={(event) => form.setData('prefix', event.target.value)} value={form.data.prefix} error={form.errors.prefix} />
                <FormField autoComplete="given-name" id="first_name" label="First name" maxLength={100} onChange={(event) => form.setData('first_name', event.target.value)} required value={form.data.first_name} error={form.errors.first_name} />
                <FormField id="middle_name" label="Middle name" maxLength={100} onChange={(event) => form.setData('middle_name', event.target.value)} value={form.data.middle_name} error={form.errors.middle_name} />
                <FormField autoComplete="family-name" id="last_name" label="Last name" maxLength={100} onChange={(event) => form.setData('last_name', event.target.value)} required value={form.data.last_name} error={form.errors.last_name} />
                <FormField autoComplete="honorific-suffix" id="suffix" label="Suffix" maxLength={20} onChange={(event) => form.setData('suffix', event.target.value)} value={form.data.suffix} error={form.errors.suffix} />
            </div></div>
            <div><h2 className="text-base font-semibold text-slate-900 dark:text-slate-100">Work and contact details</h2><div className="mt-4 grid gap-4 sm:grid-cols-2">
                <FormField autoComplete="organization" id="organization" label="Organization" maxLength={160} onChange={(event) => form.setData('organization', event.target.value)} value={form.data.organization} error={form.errors.organization} />
                <FormField id="title" label="Job title" maxLength={100} onChange={(event) => form.setData('title', event.target.value)} value={form.data.title} error={form.errors.title} />
                <FormField autoComplete="email" id="email" label="Email" maxLength={255} onChange={(event) => form.setData('email', event.target.value)} type="email" value={form.data.email} error={form.errors.email} />
                <FormField autoComplete="tel" id="phone" label="Phone" maxLength={40} onChange={(event) => form.setData('phone', event.target.value)} type="tel" value={form.data.phone} error={form.errors.phone} />
            </div></div>
            <div><label className="mb-2 block text-sm font-medium text-slate-900 dark:text-slate-100" htmlFor="notes">Notes</label><textarea aria-describedby={form.errors.notes ? 'notes-error' : undefined} aria-invalid={Boolean(form.errors.notes)} className={`block min-h-28 w-full rounded-lg border bg-slate-50 dark:bg-slate-800 p-3 text-sm text-slate-900 dark:text-slate-100 focus:bg-white dark:focus:bg-slate-800 focus:ring-brand ${form.errors.notes ? 'border-red-500 focus:border-red-500' : 'border-slate-300 dark:border-slate-700 focus:border-brand'}`} id="notes" maxLength={5000} onChange={(event) => form.setData('notes', event.target.value)} value={form.data.notes} />{form.errors.notes && <p className="mt-2 text-sm text-red-700 dark:text-red-300" id="notes-error" role="alert">{form.errors.notes}</p>}</div>
            <div>
                <label className="mb-2 block text-sm font-medium text-slate-900 dark:text-slate-100" htmlFor="attachments">Attachments</label>
                <input accept=".pdf,.png,.jpg,.jpeg,application/pdf,image/png,image/jpeg" aria-describedby="attachments-help" className="block w-full cursor-pointer rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-sm text-slate-700 dark:text-slate-200 file:mr-4 file:border-0 file:bg-slate-100 dark:file:bg-slate-700 file:px-4 file:py-3 file:font-semibold file:text-slate-700 dark:file:text-slate-200 hover:file:bg-slate-200 dark:hover:file:bg-slate-600" id="attachments" multiple name="attachments[]" onChange={(event) => form.setData('attachments', Array.from(event.target.files ?? []))} type="file" />
                <p className="mt-2 text-xs text-slate-500 dark:text-slate-400" id="attachments-help">PDF, PNG, or JPEG. Up to five files per save, 10 MB each.</p>
                {attachmentErrors.map((message, index) => <p className="mt-2 text-sm text-red-700 dark:text-red-300" key={index} role="alert">{message}</p>)}
                {attachments.length > 0 && <ul className="mt-3 divide-y divide-slate-100 dark:divide-slate-800 rounded-lg border border-slate-200 dark:border-slate-800">{attachments.map((attachment) => <li className="flex flex-wrap items-center justify-between gap-3 px-3 py-2.5 text-sm" key={attachment.id}><span className="min-w-0 break-all font-medium text-slate-700 dark:text-slate-200">{attachment.original_name}<span className="ml-2 text-xs font-normal text-slate-500 dark:text-slate-400">{(attachment.size / 1024 / 1024).toFixed(1)} MB</span></span><span className="flex gap-3"><a className="font-semibold text-brand hover:underline" href={`/contacts/${contact?.id}/attachments/${attachment.id}`}>Download</a><button aria-label={`Delete ${attachment.original_name}`} className="font-semibold text-red-700 dark:text-red-300 hover:underline" onClick={() => setAttachmentToDelete(attachment)} type="button">Delete</button></span></li>)}</ul>}
            </div>
            <div className="flex flex-col-reverse gap-3 border-t border-slate-100 dark:border-slate-800 pt-5 sm:flex-row sm:justify-end"><Link className="inline-flex items-center justify-center rounded-lg border border-slate-300 dark:border-slate-700 px-5 py-3 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800" href="/contacts">Cancel</Link><Button disabled={form.processing} type="submit">{form.processing ? 'Saving…' : contact ? 'Save changes' : 'Save contact'}</Button></div>
        </form>
        <ConfirmDialog
            confirmLabel="Delete attachment"
            description={<>Delete <strong>{attachmentToDelete?.original_name}</strong>? This cannot be undone.</>}
            onCancel={() => setAttachmentToDelete(null)}
            onConfirm={() => {
                if (contact && attachmentToDelete) router.delete(`/contacts/${contact.id}/attachments/${attachmentToDelete.id}`, { preserveScroll: true });
                setAttachmentToDelete(null);
            }}
            open={attachmentToDelete !== null}
            title="Delete this attachment?"
        />
    </>;
}
