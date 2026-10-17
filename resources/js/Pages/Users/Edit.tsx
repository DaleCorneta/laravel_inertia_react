import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Button from '../../Components/Auth/Button';
import FormField from '../../Components/Auth/FormField';
import RoleAssignment from '../../Components/Users/RoleAssignment';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';

type Person = {
    id: number;
    prefix: string | null;
    first_name: string;
    middle_name: string | null;
    last_name: string;
    suffix: string | null;
    email: string;
    email_verified: boolean;
    role_ids: number[];
};

type EditProps = {
    auth: { user: { display_name: string; email: string; can_manage_users: boolean; can_update_users: boolean; can_manage_roles?: boolean } | null };
    person: Person;
    available_roles: { id: number; name: string }[];
};

export default function Edit({ auth, person, available_roles }: EditProps) {
    const form = useForm({
        prefix: person.prefix ?? '',
        first_name: person.first_name,
        middle_name: person.middle_name ?? '',
        last_name: person.last_name,
        suffix: person.suffix ?? '',
        email: person.email,
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.put(`/users/${person.id}`);
    };

    return (
        <>
            <Head title={`Edit ${person.first_name}`} />
            <AuthenticatedLayout user={auth.user}>
                <section className="mx-auto min-w-0 w-full max-w-[1600px] py-4 sm:py-6">
                    <Link className="text-sm font-semibold text-brand hover:underline" href={`/users/${person.id}`}>← Back to user</Link>
                    <div className="mb-7 mt-6">
                        <p className="font-brand text-xs font-semibold uppercase tracking-[0.18em] text-brand">Workspace administration</p>
                        <h1 className="mt-3 font-display text-3xl font-semibold tracking-tight text-slate-950 dark:text-slate-100 sm:text-4xl">Edit user</h1>
                        <p className="mt-3 max-w-2xl text-base leading-7 text-slate-600 dark:text-slate-300">Update the profile for {person.first_name}. Changing the email address requires verification at the new address.</p>
                    </div>

                    <form className="space-y-5 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-6" onSubmit={submit}>
                        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            <FormField autoComplete="honorific-prefix" id="prefix" label="Prefix (optional)" maxLength={20} onChange={(event) => form.setData('prefix', event.target.value)} value={form.data.prefix} error={form.errors.prefix} />
                            <FormField autoComplete="given-name" id="first_name" label="First name" maxLength={100} onChange={(event) => form.setData('first_name', event.target.value)} required value={form.data.first_name} error={form.errors.first_name} />
                            <FormField autoComplete="additional-name" id="middle_name" label="Middle name (optional)" maxLength={100} onChange={(event) => form.setData('middle_name', event.target.value)} value={form.data.middle_name} error={form.errors.middle_name} />
                            <FormField autoComplete="family-name" id="last_name" label="Last name" maxLength={100} onChange={(event) => form.setData('last_name', event.target.value)} required value={form.data.last_name} error={form.errors.last_name} />
                            <FormField autoComplete="honorific-suffix" id="suffix" label="Suffix (optional)" maxLength={20} onChange={(event) => form.setData('suffix', event.target.value)} value={form.data.suffix} error={form.errors.suffix} />
                            <FormField autoComplete="email" id="email" label="Email address" maxLength={255} onChange={(event) => form.setData('email', event.target.value)} required type="email" value={form.data.email} error={form.errors.email} />
                        </div>
                        {person.email_verified && form.data.email.trim().toLowerCase() !== person.email.toLowerCase() && (
                            <p className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200" role="status">Saving a new email address will clear verification until the owner confirms the new address.</p>
                        )}
                        <div className="flex flex-col-reverse gap-3 border-t border-slate-100 pt-6 dark:border-slate-800 sm:flex-row sm:justify-end">
                            <Link className="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800" href={`/users/${person.id}`}>Cancel</Link>
                            <Button disabled={form.processing} type="submit">{form.processing ? 'Saving changes…' : 'Save changes'}</Button>
                        </div>
                    </form>
                    {auth.user?.can_manage_roles && <RoleAssignment roleIds={person.role_ids} roles={available_roles} userId={person.id} />}
                </section>
            </AuthenticatedLayout>
        </>
    );
}
