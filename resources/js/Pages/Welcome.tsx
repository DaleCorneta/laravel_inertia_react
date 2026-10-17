import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '../Layouts/AppLayout';

type WelcomeProps = {
    auth: {
        user: { display_name: string; email: string } | null;
    };
};

export default function Welcome({ auth }: WelcomeProps) {
    return (
        <>
            <Head title="Welcome" />
            <AppLayout>
                <header className="flex items-center justify-between gap-5 border-b border-slate-200 dark:border-slate-800 pb-6">
                    <div className="flex items-center gap-3">
                        <div aria-label="Company logo placeholder" className="grid size-11 place-items-center rounded-xl bg-brand text-[10px] font-bold tracking-wider text-white" role="img">
                            LOGO
                        </div>
                        <div>
                            <p className="font-brand text-sm font-bold tracking-wide text-slate-900 dark:text-slate-100">YOUR COMPANY</p>
                            <p className="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Business workspace</p>
                        </div>
                    </div>

                    <div className="flex items-center gap-3 sm:gap-5">
                        {auth.user ? (
                            <>
                                <Link className="hidden text-sm font-medium text-slate-600 dark:text-slate-300 hover:text-brand sm:inline" href="/dashboard">
                                    Dashboard
                                </Link>
                                <span className="hidden text-sm text-slate-500 dark:text-slate-400 md:inline">{auth.user.display_name}</span>
                                <button
                                    className="rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-sm font-semibold text-slate-700 dark:text-slate-200 shadow-sm transition hover:bg-slate-50 dark:hover:bg-slate-800 focus:outline-none focus:ring-4 focus:ring-slate-100 dark:focus:ring-slate-700"
                                    onClick={() => router.delete('/logout')}
                                    type="button"
                                >
                                    Sign out
                                </button>
                            </>
                        ) : (
                            <Link className="rounded-lg bg-brand px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand/90 focus:outline-none focus:ring-4 focus:ring-brand/20" href="/login">
                                Sign in
                            </Link>
                        )}
                    </div>
                </header>

                <section className="grid items-center gap-8 py-8 sm:py-10 lg:grid-cols-[1.1fr_0.9fr] lg:gap-10 xl:py-14">
                    <div className="max-w-2xl">
                        <span className="inline-flex items-center rounded-full border border-brand/20 bg-brand/5 px-3 py-1.5 font-brand text-xs font-semibold uppercase tracking-[0.16em] text-brand">
                            Laravel · Inertia · React
                        </span>
                        <h1 className="mt-5 font-display text-4xl font-semibold leading-tight tracking-tight text-slate-950 dark:text-slate-100 sm:text-5xl lg:text-6xl">
                            A better starting point for your next application.
                        </h1>
                        <p className="mt-6 max-w-xl text-lg leading-8 text-slate-600 dark:text-slate-300">
                            A secure, reusable business application foundation with the essentials already in place, ready to grow around your team.
                        </p>
                        <div className="mt-7 flex flex-wrap items-center gap-3">
                            <Link className="inline-flex items-center justify-center rounded-lg bg-brand px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-brand/90 focus:outline-none focus:ring-4 focus:ring-brand/20" href={auth.user ? '/dashboard' : '/login'}>
                                {auth.user ? 'Open dashboard' : 'Continue to sign in'}
                            </Link>
                            <span className="text-sm text-slate-500 dark:text-slate-400">Thoughtful tools for everyday work.</span>
                        </div>
                        <div className="mt-8 grid max-w-lg grid-cols-2 gap-4 border-t border-slate-200 dark:border-slate-800 pt-5">
                            <div>
                                <p className="font-display text-lg font-semibold text-slate-900 dark:text-slate-100">Secure by default</p>
                                <p className="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400">Built around server-side access controls.</p>
                            </div>
                            <div>
                                <p className="font-display text-lg font-semibold text-slate-900 dark:text-slate-100">Ready to extend</p>
                                <p className="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400">A clear base for the modules you need.</p>
                            </div>
                        </div>
                    </div>

                    <div aria-label="Sample workspace preview" className="relative mx-auto w-full max-w-xl" role="img">
                        <div className="absolute -inset-5 -z-10 rounded-[2rem] bg-brand/10 blur-2xl" />
                        <div className="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-2xl shadow-slate-900/10">
                            <div className="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 px-5 py-4 sm:px-6">
                                <div>
                                    <p className="font-brand text-xs font-bold uppercase tracking-[0.14em] text-slate-400 dark:text-slate-500">Workspace preview</p>
                                    <p className="mt-1 font-display text-base font-semibold text-slate-900 dark:text-slate-100">Your overview</p>
                                </div>
                                <span className="rounded-lg bg-brand/10 px-3 py-1.5 text-xs font-semibold text-brand">Starter</span>
                            </div>
                            <div className="grid grid-cols-2 gap-3 p-5 sm:gap-4 sm:p-6">
                                <div className="rounded-xl border border-slate-200 dark:border-slate-800 p-4">
                                    <div className="mb-4 size-9 rounded-lg bg-brand/10" />
                                    <p className="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">People</p>
                                    <p className="mt-1 font-display text-xl font-semibold text-slate-900 dark:text-slate-100">Your team</p>
                                </div>
                                <div className="rounded-xl border border-slate-200 dark:border-slate-800 p-4">
                                    <div className="mb-4 size-9 rounded-lg bg-emerald-50 dark:bg-emerald-950" />
                                    <p className="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Workspace</p>
                                    <p className="mt-1 font-display text-xl font-semibold text-slate-900 dark:text-slate-100">Your way</p>
                                </div>
                                <div className="col-span-2 rounded-xl bg-slate-50 dark:bg-slate-950 p-4 sm:p-5">
                                    <div className="flex items-center justify-between gap-4">
                                        <div>
                                            <p className="font-medium text-slate-900 dark:text-slate-100">A foundation that fits</p>
                                            <p className="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400">Add your own workflows when you’re ready.</p>
                                        </div>
                                        <div className="hidden size-14 shrink-0 rounded-full border-[6px] border-brand/20 border-t-brand sm:block" />
                                    </div>
                                </div>
                            </div>
                            <div className="border-t border-slate-100 dark:border-slate-800 px-5 py-3 text-xs text-slate-400 dark:text-slate-500 sm:px-6">
                                Placeholder preview · Replace with your application’s content
                            </div>
                        </div>
                    </div>
                </section>
            </AppLayout>
        </>
    );
}
