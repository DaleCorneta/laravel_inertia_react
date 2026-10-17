import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import AuthLayout from '../../Layouts/AuthLayout';
import Button from '../../Components/Auth/Button';
import FormField from '../../Components/Auth/FormField';

type ForgotPasswordProps = {
    flash: { status: string | null };
};

export default function ForgotPassword(_props: ForgotPasswordProps) {
    const form = useForm({ email: '' });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post('/forgot-password');
    }

    return (
        <>
            <Head title="Forgot password" />
            <AuthLayout>
                <div>
                    <span className="inline-flex items-center rounded-full bg-brand/10 px-3 py-1.5 font-brand text-xs font-semibold uppercase tracking-[0.14em] text-brand">
                        Account recovery
                    </span>
                    <h2 className="mt-5 font-display text-3xl font-semibold tracking-tight text-slate-950 dark:text-slate-100">Reset your password</h2>
                    <p className="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">
                        Enter the email address connected to your account. We’ll send a reset link if it exists.
                    </p>
                </div>


                <form className="mt-6 space-y-4" onSubmit={submit}>
                    <FormField
                        autoComplete="email"
                        error={form.errors.email}
                        id="email"
                        label="Email address"
                        name="email"
                        onChange={(event) => form.setData('email', event.target.value)}
                        placeholder="you@company.com"
                        required
                        type="email"
                        value={form.data.email}
                    />

                    <Button
                        className="w-full"
                        disabled={form.processing}
                        type="submit"
                    >
                        {form.processing ? 'Sending…' : 'Send reset link'}
                    </Button>
                </form>

                <Link className="mt-6 inline-flex items-center text-sm font-semibold text-slate-600 hover:text-brand dark:text-slate-300" href="/login">
                    ← Back to sign in
                </Link>
            </AuthLayout>
        </>
    );
}
