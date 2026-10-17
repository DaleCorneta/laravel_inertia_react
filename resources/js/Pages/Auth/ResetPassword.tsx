import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import AuthLayout from '../../Layouts/AuthLayout';
import Button from '../../Components/Auth/Button';
import FormField from '../../Components/Auth/FormField';

type ResetPasswordProps = {
    email: string;
    token: string;
};

export default function ResetPassword({ email, token }: ResetPasswordProps) {
    const form = useForm({
        email,
        token,
        password: '',
        password_confirmation: '',
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post('/reset-password');
    }

    return (
        <>
            <Head title="Choose a new password" />
            <AuthLayout>
                <div>
                    <span className="inline-flex items-center rounded-full bg-brand/10 px-3 py-1.5 font-brand text-xs font-semibold uppercase tracking-[0.14em] text-brand">
                        Secure your account
                    </span>
                    <h2 className="mt-5 font-display text-3xl font-semibold tracking-tight text-slate-950 dark:text-slate-100">Choose a new password</h2>
                    <p className="mt-2 break-all text-sm leading-6 text-slate-600 dark:text-slate-300">Create a new password for {email}.</p>
                </div>

                <form className="mt-6 space-y-4" onSubmit={submit}>
                    <FormField
                        autoComplete="new-password"
                        error={form.errors.password}
                        id="password"
                        label="New password"
                        name="password"
                        onChange={(event) => form.setData('password', event.target.value)}
                        placeholder="Enter a new password"
                        required
                        type="password"
                        value={form.data.password}
                    />

                    <FormField
                        autoComplete="new-password"
                        error={form.errors.password_confirmation}
                        id="password_confirmation"
                        label="Confirm new password"
                        name="password_confirmation"
                        onChange={(event) => form.setData('password_confirmation', event.target.value)}
                        placeholder="Re-enter your new password"
                        required
                        type="password"
                        value={form.data.password_confirmation}
                    />

                    {form.errors.email && <p className="text-sm text-red-700 dark:text-red-300" role="alert">{form.errors.email}</p>}

                    <Button
                        className="w-full"
                        disabled={form.processing}
                        type="submit"
                    >
                        {form.processing ? 'Saving…' : 'Save new password'}
                    </Button>
                </form>

                <Link className="mt-6 inline-flex items-center text-sm font-semibold text-slate-600 hover:text-brand dark:text-slate-300" href="/login">
                    ← Back to sign in
                </Link>
            </AuthLayout>
        </>
    );
}
