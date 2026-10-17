import { Head, Link } from '@inertiajs/react';
import Icon from '../../Components/Icon';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';

type Permission = { id: number; name: string };

export default function Index({ auth, groups }: {
    auth: { user: { display_name: string; email: string; can_manage_users: boolean; can_view_roles: boolean; can_view_permissions: boolean } | null };
    groups: Record<string, Permission[]>;
}) {
    const permissionCount = Object.values(groups).reduce((count, permissions) => count + permissions.length, 0);

    return (
        <>
            <Head title="Permission catalog" />
            <AuthenticatedLayout user={auth.user}>
                <section className="mx-auto min-w-0 w-full max-w-[1600px] py-4 sm:py-6">
                    <div className="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                        <div className="min-w-0">
                            <p className="inline-flex items-center gap-2 rounded-full bg-brand/10 px-3 py-1.5 font-brand text-xs font-semibold uppercase tracking-[0.14em] text-brand">
                                <Icon className="size-4" name="shield" /> Access control
                            </p>
                            <h1 className="mt-3 font-display text-3xl font-semibold tracking-tight text-slate-950 dark:text-slate-100 sm:text-4xl">Permission catalog</h1>
                            <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-600 dark:text-slate-300 sm:text-base">Review the actions available to roles, organized by area of the system.</p>
                        </div>
                        <div className="flex flex-wrap items-center gap-2 sm:shrink-0">
                            <span className="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 text-sm font-medium text-slate-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300">
                                <span className="font-semibold text-slate-900 dark:text-slate-100">{permissionCount}</span> {permissionCount === 1 ? 'permission' : 'permissions'}
                            </span>
                            <Link className="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-brand px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-brand/90" href="/roles">
                                <Icon className="size-4" name="roles" /> Manage roles
                            </Link>
                        </div>
                    </div>

                    <div className="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                        {Object.entries(groups).map(([group, permissions]) => (
                            <section className="min-w-0 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-5" key={group}>
                                <div className="flex items-center justify-between gap-3 border-b border-slate-100 pb-3 dark:border-slate-800">
                                    <div className="flex min-w-0 items-center gap-3">
                                        <span className="grid size-9 shrink-0 place-items-center rounded-lg bg-brand/10 text-brand">
                                            <Icon className="size-4" name="shield" />
                                        </span>
                                        <h2 className="truncate text-base font-semibold capitalize text-slate-900 dark:text-slate-100">{group.replaceAll('-', ' ')}</h2>
                                    </div>
                                    <span className="shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold tabular-nums text-slate-600 dark:bg-slate-800 dark:text-slate-300">{permissions.length}</span>
                                </div>
                                <ul className="mt-4 flex flex-wrap gap-2">
                                    {permissions.map((permission) => (
                                        <li className="max-w-full break-all rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 font-mono text-xs leading-5 text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300" key={permission.id}>
                                            {permission.name}
                                        </li>
                                    ))}
                                </ul>
                            </section>
                        ))}
                        {Object.keys(groups).length === 0 && (
                            <div className="rounded-2xl border border-dashed border-slate-300 bg-white px-5 py-10 text-center dark:border-slate-700 dark:bg-slate-900 sm:col-span-2 2xl:col-span-4">
                                <span className="mx-auto grid size-11 place-items-center rounded-xl bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400"><Icon className="size-5" name="shield" /></span>
                                <h2 className="mt-3 text-sm font-semibold text-slate-900 dark:text-slate-100">No permissions available</h2>
                                <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">The permission catalog is currently empty.</p>
                            </div>
                        )}
                    </div>
                </section>
            </AuthenticatedLayout>
        </>
    );
}
