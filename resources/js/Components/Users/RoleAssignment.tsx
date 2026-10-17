import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Button from '../Auth/Button';

type Role = { id: number; name: string };

export default function RoleAssignment({ userId, roleIds, roles }: { userId: number; roleIds: number[]; roles: Role[] }) {
    const form = useForm({ role_ids: roleIds });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.put(`/users/${userId}/roles`, { preserveScroll: true });
    };

    return (
        <section className="mt-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-6">
            <div>
                <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100">Roles</h2>
                <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">Roles determine which protected actions this account can perform.</p>
            </div>
            <form className="mt-5" onSubmit={submit}>
                <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    {roles.map((role) => (
                        <label className="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 px-3 py-3 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800" key={role.id}>
                            <input
                                checked={form.data.role_ids.includes(role.id)}
                                className="size-4 rounded border-slate-300 text-brand focus:ring-brand dark:border-slate-600"
                                onChange={() => form.setData('role_ids', form.data.role_ids.includes(role.id)
                                    ? form.data.role_ids.filter((id) => id !== role.id)
                                    : [...form.data.role_ids, role.id])}
                                type="checkbox"
                            />
                            <span className="capitalize">{role.name}</span>
                        </label>
                    ))}
                </div>
                {form.errors.role_ids && <p className="mt-3 text-sm text-red-700 dark:text-red-300">{form.errors.role_ids}</p>}
                <div className="mt-5 flex justify-end">
                    <Button disabled={form.processing} type="submit">{form.processing ? 'Saving…' : 'Save roles'}</Button>
                </div>
            </form>
        </section>
    );
}
