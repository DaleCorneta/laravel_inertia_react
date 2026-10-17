import type { PropsWithChildren } from 'react';
import FeedbackToasts from '../Components/FeedbackToasts';

export default function AppLayout({ children }: PropsWithChildren) {
    return (
        <div className="min-h-screen bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
            <FeedbackToasts />
            <main className="mx-auto min-h-screen w-full max-w-[1680px] px-4 py-5 sm:px-6 sm:py-7 xl:px-8">
                {children}
            </main>
        </div>
    );
}
