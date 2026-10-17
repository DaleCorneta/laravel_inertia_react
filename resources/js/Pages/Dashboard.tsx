import { Head, Link } from '@inertiajs/react';
import AuthenticatedLayout from '../Layouts/AuthenticatedLayout';

type DashboardProps = {
    auth: {
        user: { first_name: string; display_name: string; email: string; can_manage_users: boolean } | null;
    };
    flash: { status: string | null };
};

export default function Dashboard({ auth }: DashboardProps) {
    return (
        <>
            <Head title="Dashboard" />
            <AuthenticatedLayout user={auth.user}>
                <section className="mx-auto min-w-0 w-full max-w-[1600px] py-4 sm:py-6">
                    <div className="flex flex-wrap items-end justify-between gap-5">
                        <div>
                            <p className="font-brand text-xs font-semibold uppercase tracking-[0.18em] text-brand">Workspace overview</p>
                            <h1 className="mt-3 font-display text-3xl font-semibold tracking-tight text-slate-950 dark:text-slate-100 sm:text-4xl">
                                Welcome back, {auth.user?.first_name}.
                            </h1>
                            <p className="mt-3 max-w-2xl text-base leading-7 text-slate-600 dark:text-slate-300">
                                Your reusable application foundation is ready. Add the tools and workflows your team needs next.
                            </p>
                        </div>
                        <span className="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-300">
                            <span className="size-1.5 rounded-full bg-emerald-500" />
                            Foundation ready
                        </span>
                    </div>

                    <div className="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        {[
                            { label: 'Team members', value: 'Ready when you are', detail: 'User management will live here.' },
                            { label: 'Your modules', value: 'Start with what matters', detail: 'Add business features as you grow.' },
                            { label: 'Account security', value: 'Protected access', detail: 'Authentication is active for your account.' },
                        ].map((item) => (
                            <article className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-6" key={item.label}>
                                <p className="text-sm font-medium text-slate-500 dark:text-slate-400">{item.label}</p>
                                <p className="mt-4 font-display text-lg font-semibold text-slate-900 dark:text-slate-100">{item.value}</p>
                                <p className="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">{item.detail}</p>
                            </article>
                        ))}
                    </div>

                    <section className="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div className="grid gap-6 p-4 sm:p-6 lg:grid-cols-[1fr_auto] lg:items-center">
                            <div className="max-w-2xl">
                                <span className="inline-flex rounded-lg bg-brand/10 px-2.5 py-1 text-xs font-semibold uppercase tracking-wide text-brand">
                                    Getting started
                                </span>
                                <h2 className="mt-4 font-display text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-100">
                                    Make this workspace your own.
                                </h2>
                                <p className="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-300 sm:text-base sm:leading-7">
                                    This dashboard is a clean scaffold. The next steps can add your navigation, team tools, and brand settings without locking you into a fixed product.
                                </p>
                            </div>
                            <Link className="inline-flex items-center justify-center rounded-lg bg-brand px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand/90 focus:outline-none focus:ring-4 focus:ring-brand/20" href="/">
                                View welcome page
                            </Link>
                        </div>
                    </section>
                </section>
            </AuthenticatedLayout>
        </>
    );
}
