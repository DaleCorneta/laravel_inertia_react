import { Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Button from '../Auth/Button';

export type PermissionOption = { id: number; name: string; group: string };
export type RoleData = { id: number; name: string; permission_ids: number[]; is_system: boolean };

export default function RoleForm({ role, permissions, canManagePermissions }: { role?: RoleData; permissions: PermissionOption[]; canManagePermissions: boolean }) {
    const form = useForm({ name: role?.name ?? '', permission_ids: role?.permission_ids ?? [] as number[] });
    const grouped = permissions.reduce<Record<string, PermissionOption[]>>((groups, permission) => {
        (groups[permission.group] ??= []).push(permission);

        return groups;
    }, {});

    const togglePermission = (id: number) => {
        form.setData('permission_ids', form.data.permission_ids.includes(id)
            ? form.data.permission_ids.filter((permissionId) => permissionId !== id)
            : [...form.data.permission_ids, id]);
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (role) form.put(`/roles/${role.id}`);
        else form.post('/roles');
    };

    return (
        <form className="space-y-5" onSubmit={submit}>
            <div>
                <label className="mb-2 block text-sm font-semibold text-slate-700 dark:text-slate-200" htmlFor="role-name">Role name</label>
                <input
                    autoComplete="off"
                    className="block w-full rounded-lg border border-slate-300 bg-white p-3 text-sm text-slate-900 shadow-sm focus:border-brand focus:ring-brand dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                    id="role-name"
                    maxLength={100}
                    onChange={(event) => form.setData('name', event.target.value)}
                    readOnly={role?.is_system}
                    required
                    value={form.data.name}
                />
                {role?.is_system && <p className="mt-2 text-xs text-slate-500 dark:text-slate-400">The administrator role name is reserved so setup commands keep working.</p>}
                {form.errors.name && <p className="mt-2 text-sm text-red-700 dark:text-red-300">{form.errors.name}</p>}
            </div>

            <section>
                <div className="mb-3 flex flex-wrap items-end justify-between gap-2">
                    <div>
                        <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100">Permissions</h2>
                        <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">Choose the actions this role can perform.</p>
                    </div>
                    {role?.is_system && <span className="text-xs font-medium text-brand">Core administrator access is preserved</span>}
                </div>
                {form.errors.permission_ids && <p className="mb-3 text-sm text-red-700 dark:text-red-300">{form.errors.permission_ids}</p>}
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {Object.entries(grouped).map(([group, items]) => (
                        <fieldset className="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900" key={group}>
                            <legend className="px-1 text-sm font-semibold capitalize text-slate-800 dark:text-slate-200">{group.replaceAll('-', ' ')}</legend>
                            <div className="mt-2 space-y-2">
                                {items.map((permission) => {
                                    const required = role?.is_system && ['users.view', 'users.create', 'users.update', 'roles.view', 'roles.manage', 'permissions.view', 'permissions.manage'].includes(permission.name);
                                    const checked = required || form.data.permission_ids.includes(permission.id);

                                    return (
                                        <label className="flex cursor-pointer items-start gap-3 rounded-lg px-2 py-2 hover:bg-slate-50 dark:hover:bg-slate-800" key={permission.id}>
                                            <input
                                                checked={checked}
                                                className="mt-0.5 size-4 rounded border-slate-300 text-brand focus:ring-brand dark:border-slate-600"
                                                disabled={required || !canManagePermissions}
                                                onChange={() => togglePermission(permission.id)}
                                                type="checkbox"
                                            />
                                            <span>
                                                <span className="block text-sm font-medium text-slate-800 dark:text-slate-200">{permission.name}</span>
                                                {required && <span className="mt-0.5 block text-xs text-slate-500 dark:text-slate-400">Required for administrator access</span>}
                                            </span>
                                        </label>
                                    );
                                })}
                            </div>
                        </fieldset>
                    ))}
                </div>
                {!canManagePermissions && <p className="mt-3 text-xs text-slate-500 dark:text-slate-400">You can manage role names. A user with permission management access must change permission assignments.</p>}
            </section>

            <div className="flex flex-wrap items-center justify-end gap-3 border-t border-slate-100 pt-5 dark:border-slate-800">
                <Link className="rounded-lg px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800" href="/roles">Cancel</Link>
                <Button disabled={form.processing} type="submit">{form.processing ? 'Saving…' : role ? 'Save changes' : 'Create role'}</Button>
            </div>
        </form>
    );
}
