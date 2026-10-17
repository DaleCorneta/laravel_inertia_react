import type { PropsWithChildren } from 'react';
import FeedbackToasts from '../Components/FeedbackToasts';

function CompanyMark() {
    return (
        <div className="flex items-center gap-3">
            <div
                aria-label="Company logo placeholder"
                className="grid size-12 place-items-center rounded-2xl border border-white/15 bg-white/10 text-[10px] font-bold tracking-[0.16em] text-white shadow-sm backdrop-blur"
                role="img"
            >
                LOGO
            </div>
            <div>
                <p className="font-brand text-sm font-bold tracking-[0.12em] text-white">YOUR COMPANY</p>
                <p className="mt-0.5 text-xs text-slate-400">Business workspace</p>
            </div>
        </div>
    );
}

export default function AuthLayout({ children }: PropsWithChildren) {
    return (
        <div className="min-h-screen bg-white text-slate-900 dark:bg-slate-950 dark:text-slate-100">
            <FeedbackToasts />
            <div className="grid min-h-screen lg:grid-cols-[1.05fr_0.95fr]">
                <aside className="relative isolate hidden overflow-hidden bg-slate-950 px-10 py-12 text-white lg:flex lg:flex-col lg:justify-between xl:px-16 xl:py-14">
                    <div aria-hidden="true" className="absolute inset-0 -z-10 overflow-hidden">
                        <div className="absolute -left-36 -top-32 size-[32rem] rounded-full bg-brand/20 blur-3xl" />
                        <div className="absolute -bottom-48 -right-32 size-[34rem] rounded-full bg-cyan-400/10 blur-3xl" />
                        <div className="absolute inset-0 bg-[radial-gradient(circle_at_1px_1px,rgba(255,255,255,0.08)_1px,transparent_0)] [background-size:28px_28px] opacity-40" />
                    </div>

                    <CompanyMark />

                    <div className="relative my-8 max-w-xl xl:my-12">
                        <p className="font-brand text-xs font-semibold uppercase tracking-[0.22em] text-white">
                            A calmer way to work
                        </p>
                        <h1 className="mt-6 font-display text-4xl font-semibold leading-tight tracking-tight xl:text-5xl">
                            Everything your team needs, in one place.
                        </h1>
                        <p className="mt-6 max-w-lg text-base leading-7 text-slate-300">
                            A secure, flexible workspace to organize people, content, and everyday operations as your business grows.
                        </p>

                        <div className="mt-7 grid gap-3 sm:grid-cols-2">
                            <div className="rounded-xl border border-white/10 bg-white/[0.06] p-4 backdrop-blur-sm">
                                <span className="mb-3 grid size-9 place-items-center rounded-lg bg-brand/20 text-sm font-semibold text-white">01</span>
                                <p className="font-medium text-white">One clear workspace</p>
                                <p className="mt-1.5 text-sm leading-5 text-slate-400">Keep the work that matters easy to find.</p>
                            </div>
                            <div className="rounded-xl border border-white/10 bg-white/[0.06] p-4 backdrop-blur-sm">
                                <span className="mb-3 grid size-9 place-items-center rounded-lg bg-brand/20 text-sm font-semibold text-white">02</span>
                                <p className="font-medium text-white">Built to grow</p>
                                <p className="mt-1.5 text-sm leading-5 text-slate-400">Start simple and add tools when you need them.</p>
                            </div>
                        </div>
                    </div>

                    <p className="text-xs text-slate-500">A reusable foundation for your next great application.</p>
                </aside>

                <main className="flex min-h-screen flex-col items-center justify-center bg-slate-50/70 px-4 py-7 dark:bg-slate-950 sm:px-7 lg:px-10 xl:px-14">
                    <div className="mb-7 w-full max-w-md lg:hidden">
                        <CompanyMark />
                    </div>
                    <div className="w-full max-w-lg rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-7">{children}</div>
                    <p className="mt-7 text-center text-xs text-slate-400 dark:text-slate-500">
                        Secure access to your business workspace
                    </p>
                </main>
            </div>
        </div>
    );
}
