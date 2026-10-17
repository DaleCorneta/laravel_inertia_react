import { Head, router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import AuthLayout from '../../Layouts/AuthLayout';
import Button from '../../Components/Auth/Button';

type VerifyEmailProps = {
    auth: {
        user: { email: string } | null;
    };
    flash: { status: string | null };
};

export default function VerifyEmail({ auth }: VerifyEmailProps) {
    const form = useForm({});

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post('/email/verification-notification');
    }

    return (
        <>
            <Head title="Verify your email" />
            <AuthLayout>
                <div>
                    <span className="inline-flex items-center rounded-full bg-brand/10 px-3 py-1.5 font-brand text-xs font-semibold uppercase tracking-[0.14em] text-brand">
                        One more step
                    </span>
                    <h2 className="mt-5 font-display text-3xl font-semibold tracking-tight text-slate-950 dark:text-slate-100">Verify your email</h2>
                    <p className="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-300">
                        We sent a verification link to <span className="font-semibold text-slate-800 dark:text-slate-200">{auth.user?.email}</span>.
                        Open that email and follow the link to continue to your workspace.
                    </p>
                </div>


                <form className="mt-6" onSubmit={submit}>
                    <Button
                        className="w-full"
                        disabled={form.processing}
                        type="submit"
                    >
                        {form.processing ? 'Sending…' : 'Resend verification email'}
                    </Button>
                </form>

                <Button
                    className="mt-6 px-0 py-0 text-slate-500 shadow-none hover:bg-transparent hover:text-brand focus:ring-0"
                    variant="secondary"
                    onClick={() => router.delete('/logout')}
                    type="button"
                >
                    Sign out
                </Button>
            </AuthLayout>
        </>
    );
}
