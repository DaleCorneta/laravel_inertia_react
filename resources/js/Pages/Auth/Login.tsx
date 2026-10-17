import { Head, Link, router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import AuthLayout from '../../Layouts/AuthLayout';
import Button from '../../Components/Auth/Button';
import FormField from '../../Components/Auth/FormField';

type LoginProps = {
    flash: { status: string | null };
    mfaChallenge: boolean;
    googleLoginEnabled: boolean;
};

export default function Login({ mfaChallenge, googleLoginEnabled }: LoginProps) {
    const form = useForm({
        email: '',
        password: '',
        remember: false,
    });
    const challengeForm = useForm({ code: '' });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post('/login');
    }

    function submitChallenge(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        challengeForm.post('/login/mfa', { onSuccess: () => challengeForm.reset() });
    }

    return (
        <>
            <Head title="Sign in" />
            <AuthLayout>
                <div>
                    <span className="inline-flex items-center rounded-full bg-brand/10 px-3 py-1.5 font-brand text-xs font-semibold uppercase tracking-[0.14em] text-brand">
                        {mfaChallenge ? 'Account verification' : 'Welcome back'}
                    </span>
                    <h2 className="mt-5 font-display text-3xl font-semibold tracking-tight text-slate-950 dark:text-slate-100">
                        {mfaChallenge ? 'Verify your sign-in' : 'Sign in to your account'}
                    </h2>
                    <p className="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">
                        {mfaChallenge ? 'Enter a code from your authenticator app or use one of your recovery codes.' : 'Enter your details to access your workspace.'}
                    </p>
                </div>


                {mfaChallenge ? (
                    <form className="mt-6 space-y-4" onSubmit={submitChallenge}>
                        <FormField
                            autoComplete="one-time-code"
                            error={challengeForm.errors.code}
                            id="mfa_code"
                            label="Authenticator or recovery code"
                            maxLength={32}
                            onChange={(event) => challengeForm.setData('code', event.target.value)}
                            required
                            value={challengeForm.data.code}
                        />
                        <Button className="w-full" disabled={challengeForm.processing} type="submit">
                            {challengeForm.processing ? 'Verifying…' : 'Verify and sign in'}
                        </Button>
                        <button
                            className="w-full rounded-lg px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100 focus:outline-none focus:ring-4 focus:ring-slate-100 dark:text-slate-300 dark:hover:bg-slate-800 dark:focus:ring-slate-800"
                            onClick={() => router.delete('/login/mfa')}
                            type="button"
                        >
                            Use a different account
                        </button>
                    </form>
                ) : (
                <form className="mt-6 space-y-4" onSubmit={submit}>
                    <FormField
                        autoComplete="username"
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

                    <div>
                        <FormField
                            autoComplete="current-password"
                            error={form.errors.password}
                            id="password"
                            label="Password"
                            name="password"
                            onChange={(event) => form.setData('password', event.target.value)}
                            placeholder="Enter your password"
                            required
                            type="password"
                            value={form.data.password}
                            trailing={
                            <Link className="text-sm font-semibold text-brand hover:underline" href="/forgot-password">
                                Forgot password?
                            </Link>
                            }
                        />
                    </div>

                    <label className="flex items-center gap-2.5 text-sm text-slate-600 dark:text-slate-300">
                        <input
                            checked={form.data.remember}
                            className="size-4 rounded border-slate-300 bg-slate-50 text-brand focus:ring-2 focus:ring-brand/30 dark:border-slate-700 dark:bg-slate-800"
                            name="remember"
                            onChange={(event) => form.setData('remember', event.target.checked)}
                            type="checkbox"
                        />
                        Keep me signed in
                    </label>

                    <Button
                        className="w-full"
                        disabled={form.processing}
                        type="submit"
                    >
                        {form.processing ? 'Signing in…' : 'Sign in'}
                    </Button>
                </form>
                )}

                {googleLoginEnabled && !mfaChallenge && (
                    <div className="mt-6">
                        <div className="relative flex items-center">
                            <div className="w-full border-t border-slate-200 dark:border-slate-700" />
                            <span className="absolute left-1/2 -translate-x-1/2 bg-white px-3 text-xs font-medium uppercase tracking-wide text-slate-400 dark:bg-slate-900 dark:text-slate-500">Or continue with</span>
                        </div>
                        <a className="mt-5 flex min-h-11 w-full items-center justify-center gap-3 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-slate-100 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 dark:focus:ring-slate-800" href="/auth/google/redirect">
                            <span aria-hidden="true" className="font-display text-lg font-bold text-blue-600">G</span>
                            Continue with Google
                        </a>
                    </div>
                )}

                <p className="mt-6 text-center text-sm text-slate-500 dark:text-slate-400">
                    Need help accessing your account?{' '}
                    <Link className="font-semibold text-brand hover:underline" href="/forgot-password">Reset your password</Link>
                </p>
            </AuthLayout>
        </>
    );
}
