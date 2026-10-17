import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Button from '../../Components/Auth/Button';
import FormField from '../../Components/Auth/FormField';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';

type CreateUserProps = {
    auth: {
        user: { display_name: string; email: string; can_manage_users: boolean; can_create_users: boolean; can_manage_roles?: boolean } | null;
    };
    available_roles: { id: number; name: string }[];
};

export default function Create({ auth, available_roles }: CreateUserProps) {
    const form = useForm({
        prefix: '',
        first_name: '',
        middle_name: '',
        last_name: '',
        suffix: '',
        email: '',
        password: '',
        password_confirmation: '',
        ...(auth.user?.can_manage_roles ? { role_ids: [] as number[] } : {}),
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post('/users');
    };

    return (
        <>
            <Head title="Add user" />
            <AuthenticatedLayout user={auth.user}>
                <section className="mx-auto min-w-0 w-full max-w-[1600px] py-4 sm:py-6">
                    <div className="mb-7">
                        <Link className="text-sm font-semibold text-brand hover:underline" href="/users">← Back to users</Link>
                        <p className="mt-6 font-brand text-xs font-semibold uppercase tracking-[0.18em] text-brand">Workspace administration</p>
                        <h1 className="mt-3 font-display text-3xl font-semibold tracking-tight text-slate-950 dark:text-slate-100 sm:text-4xl">Add a user</h1>
                        <p className="mt-3 max-w-2xl text-base leading-7 text-slate-600 dark:text-slate-300">Create an account and send its owner an email verification link.</p>
                    </div>

                    <form className="space-y-5 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-6" onSubmit={submit}>
                        <div>
                            <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100">Personal information</h2>
                            <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">Enter the name the user will see in the workspace.</p>
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            <FormField autoComplete="honorific-prefix" id="prefix" label="Prefix (optional)" maxLength={20} onChange={(event) => form.setData('prefix', event.target.value)} value={form.data.prefix} error={form.errors.prefix} />
                            <FormField autoComplete="given-name" id="first_name" label="First name" maxLength={100} onChange={(event) => form.setData('first_name', event.target.value)} required value={form.data.first_name} error={form.errors.first_name} />
                            <FormField autoComplete="additional-name" id="middle_name" label="Middle name (optional)" maxLength={100} onChange={(event) => form.setData('middle_name', event.target.value)} value={form.data.middle_name} error={form.errors.middle_name} />
                            <FormField autoComplete="family-name" id="last_name" label="Last name" maxLength={100} onChange={(event) => form.setData('last_name', event.target.value)} required value={form.data.last_name} error={form.errors.last_name} />
                            <FormField autoComplete="honorific-suffix" id="suffix" label="Suffix (optional)" maxLength={20} onChange={(event) => form.setData('suffix', event.target.value)} value={form.data.suffix} error={form.errors.suffix} />
                            <FormField autoComplete="email" id="email" label="Email address" maxLength={255} onChange={(event) => form.setData('email', event.target.value)} required type="email" value={form.data.email} error={form.errors.email} />
                        </div>

                        {auth.user?.can_manage_roles && <div className="border-t border-slate-100 pt-5 dark:border-slate-800">
                            <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100">Assign roles</h2>
                            <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">Choose the access this account needs. You can update roles later.</p>
                            {available_roles.length > 0 ? <div className="mt-4 grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                                {available_roles.map((role) => (
                                    <label className="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 px-3 py-3 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800" key={role.id}>
                                        <input
                                            checked={(form.data.role_ids ?? []).includes(role.id)}
                                            className="size-4 rounded border-slate-300 text-brand focus:ring-brand dark:border-slate-600"
                                            onChange={() => form.setData('role_ids', (form.data.role_ids ?? []).includes(role.id)
                                                ? (form.data.role_ids ?? []).filter((id) => id !== role.id)
                                                : [...(form.data.role_ids ?? []), role.id])}
                                            type="checkbox"
                                        />
                                        <span className="capitalize">{role.name}</span>
                                    </label>
                                ))}
                            </div> : <p className="mt-3 text-sm text-slate-500 dark:text-slate-400">There are no assignable roles yet.</p>}
                            {form.errors.role_ids && <p className="mt-3 text-sm text-red-700 dark:text-red-300">{form.errors.role_ids}</p>}
                        </div>}

                        <div className="border-t border-slate-100 pt-5 dark:border-slate-800">
                            <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100">Sign-in details</h2>
                            <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">Use a temporary password and share it securely. The user can reset it after verification.</p>
                            <div className="mt-4 grid gap-4 sm:grid-cols-2">
                                <FormField autoComplete="new-password" id="password" label="Temporary password" onChange={(event) => form.setData('password', event.target.value)} required type="password" value={form.data.password} error={form.errors.password} />
                                <FormField autoComplete="new-password" id="password_confirmation" label="Confirm temporary password" onChange={(event) => form.setData('password_confirmation', event.target.value)} required type="password" value={form.data.password_confirmation} error={form.errors.password_confirmation} />
                            </div>
                        </div>

                        <div className="flex flex-col-reverse gap-3 border-t border-slate-100 pt-6 dark:border-slate-800 sm:flex-row sm:justify-end">
                            <Link className="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800" href="/users">Cancel</Link>
                            <Button disabled={form.processing} type="submit">{form.processing ? 'Creating account…' : 'Create user'}</Button>
                        </div>
                    </form>
                </section>
            </AuthenticatedLayout>
        </>
    );
}
