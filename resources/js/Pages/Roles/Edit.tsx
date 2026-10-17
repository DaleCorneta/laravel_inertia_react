import { Head } from '@inertiajs/react';
import type { PermissionOption, RoleData } from '../../Components/Roles/RoleForm';
import RoleForm from '../../Components/Roles/RoleForm';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';

export default function Edit({ auth, role, permissions, can_manage_permissions }: {
    auth: { user: { display_name: string; email: string; can_manage_users: boolean; can_view_roles: boolean; can_view_permissions: boolean; can_manage_permissions: boolean } | null };
    role: RoleData;
    permissions: PermissionOption[];
    can_manage_permissions: boolean;
}) {
    return (
        <>
            <Head title={`Edit ${role.name}`} />
            <AuthenticatedLayout user={auth.user}>
                <section className="mx-auto min-w-0 w-full max-w-[1600px] py-4 sm:py-6">
                    <p className="font-brand text-xs font-semibold uppercase tracking-[0.18em] text-brand">Access control</p>
                    <h1 className="mt-3 font-display text-3xl font-semibold tracking-tight text-slate-950 dark:text-slate-100">Edit {role.name}</h1>
                    <p className="mt-3 text-base text-slate-600 dark:text-slate-300">Changes apply to every user assigned to this role.</p>
                    <div className="mt-6 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-6">
                        <RoleForm canManagePermissions={can_manage_permissions} permissions={permissions} role={role} />
                    </div>
                </section>
            </AuthenticatedLayout>
        </>
    );
}
