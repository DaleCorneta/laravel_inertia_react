import { Head, Link, router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import Button from '../../Components/Auth/Button';
import ConfirmDialog from '../../Components/ConfirmDialog';
import RoleAssignment from '../../Components/Users/RoleAssignment';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';

type Person = {
    id: number;
    prefix: string | null;
    first_name: string;
    middle_name: string | null;
    last_name: string;
    suffix: string | null;
    full_name: string;
    email: string;
    email_verified: boolean;
    is_active: boolean;
    can_manage_status: boolean;
    role_ids: number[];
    role_names: string[];
    created_at: string | null;
};

type ShowProps = {
    auth: { user: { display_name: string; email: string; can_manage_users: boolean; can_update_users: boolean; can_manage_roles?: boolean } | null };
    person: Person;
    available_roles: { id: number; name: string }[];
    flash: { status?: string | null };
};

function Detail({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="border-b border-slate-100 py-4 last:border-b-0 dark:border-slate-800">
            <dt className="text-sm font-medium text-slate-500 dark:text-slate-400">{label}</dt>
            <dd className="mt-1 break-words text-sm font-semibold text-slate-900 dark:text-slate-100">{children || '—'}</dd>
        </div>
    );
}

export default function Show({ auth, person, available_roles }: ShowProps) {
    const [confirmDeactivate, setConfirmDeactivate] = useState(false);

    const updateStatus = (isActive: boolean) => {
        router.patch(`/users/${person.id}/status`, { is_active: isActive }, { preserveScroll: true });
        setConfirmDeactivate(false);
    };

    return (
        <>
            <Head title={person.full_name} />
            <AuthenticatedLayout user={auth.user}>
                <section className="mx-auto min-w-0 w-full max-w-[1600px] py-4 sm:py-6">
                    <Link className="text-sm font-semibold text-brand hover:underline" href="/users">← Back to users</Link>
                    <div className="mt-6 flex flex-wrap items-end justify-between gap-5">
                        <div>
                            <p className="font-brand text-xs font-semibold uppercase tracking-[0.18em] text-brand">User account</p>
                            <h1 className="mt-3 font-display text-3xl font-semibold tracking-tight text-slate-950 dark:text-slate-100 sm:text-4xl">{person.full_name}</h1>
                            <p className="mt-3 text-base text-slate-600 dark:text-slate-300">Account details, access status, and email verification.</p>
                        </div>
                        <div className="flex flex-wrap gap-3">
                            {person.can_manage_status && (person.is_active ? (
                                <Button onClick={() => setConfirmDeactivate(true)} type="button" variant="danger">Deactivate</Button>
                            ) : (
                                <Button onClick={() => updateStatus(true)} type="button">Activate account</Button>
                            ))}
                            {auth.user?.can_update_users && <Link className="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800" href={`/users/${person.id}/edit`}>Edit user</Link>}
                        </div>
                    </div>


                    <div className="mt-6 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-6">
                        <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100">Profile</h2>
                        <dl className="mt-3 grid gap-x-10 sm:grid-cols-2">
                            <Detail label="Full name">{person.full_name}</Detail>
                            <Detail label="Email address">{person.email}</Detail>
                            <Detail label="Account status"><span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${person.is_active ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200'}`}>{person.is_active ? 'Active' : 'Deactivated'}</span></Detail>
                            <Detail label="Email verification"><span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${person.email_verified ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-amber-50 text-amber-800 dark:bg-amber-950 dark:text-amber-300'}`}>{person.email_verified ? 'Verified' : 'Pending'}</span></Detail>
                            <Detail label="Account created">{person.created_at ? new Date(person.created_at).toLocaleDateString() : null}</Detail>
                        </dl>
                    </div>

                    {!auth.user?.can_manage_roles && <div className="mt-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-6">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100">Roles</h2>
                                <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">Roles determine which protected actions this account can perform.</p>
                            </div>
                            <div className="flex flex-wrap gap-2">{person.role_names.map((name) => <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200" key={name}>{name}</span>)}</div>
                        </div>
                    </div>}
                    {auth.user?.can_manage_roles && <RoleAssignment roleIds={person.role_ids} roles={available_roles} userId={person.id} />}
                </section>
                <ConfirmDialog
                    cancelLabel="Keep account active"
                    confirmLabel="Deactivate account"
                    description={<>This prevents <strong>{person.full_name}</strong> from signing in and ends access on their next request. You can reactivate the account later.</>}
                    onCancel={() => setConfirmDeactivate(false)}
                    onConfirm={() => updateStatus(false)}
                    open={confirmDeactivate}
                    title="Deactivate this account?"
                    tone="danger"
                />
            </AuthenticatedLayout>
        </>
    );
}
