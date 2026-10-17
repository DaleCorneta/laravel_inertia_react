import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';

type SettingsProps = {
    auth: { user: { display_name: string; email: string; can_manage_users: boolean } | null };
};

export default function Index({ auth }: SettingsProps) {
    return (
        <>
            <Head title="Settings" />
            <AuthenticatedLayout user={auth.user}>
                <section className="mx-auto min-w-0 w-full max-w-[1600px] py-4 sm:py-6">
                    <div>
                        <p className="font-brand text-xs font-semibold uppercase tracking-[0.18em] text-brand">Workspace preferences</p>
                        <h1 className="mt-3 font-display text-3xl font-semibold tracking-tight text-slate-950 dark:text-slate-100 sm:text-4xl">Settings</h1>
                        <p className="mt-3 text-base leading-7 text-slate-600 dark:text-slate-300">Workspace configuration will be managed here.</p>
                    </div>
                    <section aria-labelledby="branding-heading" className="mt-6 w-full rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-6">
                        <div className="flex items-start gap-4">
                            <span aria-hidden="true" className="grid size-11 shrink-0 place-items-center rounded-xl bg-brand/10 text-brand">
                                <svg className="size-5" fill="none" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.8" viewBox="0 0 24 24"><path d="M12 3v2m0 14v2m9-9h-2M5 12H3m15.36 6.36-1.42-1.42M7.06 7.06 5.64 5.64m12.72 0-1.42 1.42M7.06 16.94l-1.42 1.42" /><circle cx="12" cy="12" r="4" /></svg>
                            </span>
                            <div>
                                <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100" id="branding-heading">Branding</h2>
                                <p className="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">Company logo and brand colors will be configurable here. The current logo and color are placeholders for this starter template.</p>
                            </div>
                        </div>
                        <div className="mt-6 grid gap-4 rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800 sm:grid-cols-2">
                            <div><p className="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Company logo</p><p className="mt-2 text-sm font-medium text-slate-600 dark:text-slate-300">Not configured</p></div>
                            <div><p className="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Brand color</p><p className="mt-2 text-sm font-medium text-slate-600 dark:text-slate-300">Starter palette</p></div>
                        </div>
                    </section>
                </section>
            </AuthenticatedLayout>
        </>
    );
}
