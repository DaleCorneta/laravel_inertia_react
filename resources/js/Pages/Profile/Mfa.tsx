import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Button from '../../Components/Auth/Button';
import FormField from '../../Components/Auth/FormField';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';

type MfaProps = {
    auth: { user: { display_name: string; email: string; can_manage_users: boolean } | null };
    enabled: boolean;
    setup: { qr_code: string; secret: string } | null;
    recoveryCodes: string[];
    flash: { status?: string | null };
};

export default function Mfa({ auth, enabled, setup, recoveryCodes }: MfaProps) {
    const beginForm = useForm({ current_password: '' });
    const confirmForm = useForm({ current_password: '', code: '' });
    const disableForm = useForm({ current_password: '', code: '' });
    const recoveryForm = useForm({ current_password: '', code: '' });

    const beginEnrollment = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        beginForm.post('/profile/mfa/enrollment', { onSuccess: () => beginForm.reset() });
    };

    const confirmEnrollment = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        confirmForm.post('/profile/mfa/confirm', { onSuccess: () => confirmForm.reset() });
    };

    const disableMfa = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        disableForm.delete('/profile/mfa', { onSuccess: () => disableForm.reset() });
    };

    const regenerateCodes = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        recoveryForm.post('/profile/mfa/recovery-codes', { onSuccess: () => recoveryForm.reset() });
    };

    return (
        <>
            <Head title="Two-factor authentication" />
            <AuthenticatedLayout user={auth.user}>
                <section className="mx-auto min-w-0 w-full max-w-[1600px] py-4 sm:py-6">
                    <div>
                        <p className="font-brand text-xs font-semibold uppercase tracking-[0.18em] text-brand">Account security</p>
                        <h1 className="mt-3 font-display text-3xl font-semibold tracking-tight text-slate-950 dark:text-slate-100 sm:text-4xl">Two-factor authentication</h1>
                        <p className="mt-3 max-w-3xl text-base leading-7 text-slate-600 dark:text-slate-300">Use an authenticator app to add a time-based code to your account. Recovery codes let you regain access if you lose your device.</p>
                    </div>


                    {!enabled && !setup && (
                        <section aria-labelledby="setup-heading" className="mt-6 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-6">
                            <h2 className="text-lg font-semibold text-slate-900 dark:text-slate-100" id="setup-heading">Set up an authenticator</h2>
                            <p className="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">Confirm your password to create a setup code. Your authenticator secret stays encrypted in the database.</p>
                            <form className="mt-5 flex max-w-xl flex-col gap-4 sm:flex-row sm:items-end" onSubmit={beginEnrollment}>
                                <div className="w-full"><FormField autoComplete="current-password" error={beginForm.errors.current_password} id="begin_current_password" label="Current password" onChange={(event) => beginForm.setData('current_password', event.target.value)} required type="password" value={beginForm.data.current_password} /></div>
                                <Button className="shrink-0" disabled={beginForm.processing} type="submit">{beginForm.processing ? 'Preparing…' : 'Begin setup'}</Button>
                            </form>
                        </section>
                    )}

                    {!enabled && setup && (
                        <section aria-labelledby="confirm-heading" className="mt-6 grid gap-6 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 lg:grid-cols-[minmax(0,1fr)_minmax(18rem,0.8fr)] sm:p-6">
                            <div>
                                <h2 className="text-lg font-semibold text-slate-900 dark:text-slate-100" id="confirm-heading">Scan and confirm</h2>
                                <ol className="mt-3 list-decimal space-y-2 pl-5 text-sm leading-6 text-slate-600 dark:text-slate-300">
                                    <li>Open an authenticator app on your phone.</li>
                                    <li>Scan the QR code or enter the setup key manually.</li>
                                    <li>Enter the six-digit code from the app and your current password.</li>
                                </ol>
                                <div className="mt-5 inline-flex rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-white">
                                    <img alt="Authenticator setup QR code" className="size-48" src={`data:image/svg+xml;base64,${setup.qr_code}`} />
                                </div>
                                <p className="mt-4 text-sm font-medium text-slate-700 dark:text-slate-200">Setup key</p>
                                <code className="mt-1 block break-all rounded-lg bg-slate-50 p-3 text-sm text-slate-800 dark:bg-slate-800 dark:text-slate-200">{setup.secret}</code>
                            </div>
                            <form className="flex flex-col gap-4 rounded-xl bg-slate-50 p-4 dark:bg-slate-800 sm:p-5" onSubmit={confirmEnrollment}>
                                <FormField autoComplete="current-password" error={confirmForm.errors.current_password} id="confirm_current_password" label="Current password" onChange={(event) => confirmForm.setData('current_password', event.target.value)} required type="password" value={confirmForm.data.current_password} />
                                <FormField autoComplete="one-time-code" error={confirmForm.errors.code} id="confirm_code" inputMode="numeric" label="Authenticator code" maxLength={6} onChange={(event) => confirmForm.setData('code', event.target.value)} required value={confirmForm.data.code} />
                                <Button disabled={confirmForm.processing} type="submit">{confirmForm.processing ? 'Confirming…' : 'Confirm and enable'}</Button>
                            </form>
                        </section>
                    )}

                    {enabled && (
                        <div className="mt-6 grid gap-4 xl:grid-cols-2">
                            <section aria-labelledby="enabled-heading" className="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-900 dark:bg-emerald-950 sm:p-6">
                                <h2 className="text-lg font-semibold text-emerald-950 dark:text-emerald-200" id="enabled-heading">Authenticator enabled</h2>
                                <p className="mt-2 text-sm leading-6 text-emerald-900 dark:text-emerald-300">Your authenticator is connected. Keep your recovery codes somewhere safe and regenerate them if they may have been exposed.</p>
                            </section>

                            {recoveryCodes.length > 0 && (
                                <section aria-labelledby="recovery-codes-heading" className="rounded-2xl border border-amber-300 bg-amber-50 p-4 dark:border-amber-900 dark:bg-amber-950 sm:p-6">
                                    <h2 className="text-lg font-semibold text-amber-950 dark:text-amber-200" id="recovery-codes-heading">Save your recovery codes</h2>
                                    <p className="mt-2 text-sm leading-6 text-amber-900 dark:text-amber-300">These codes are shown once. Each code can be used one time in place of an authenticator code.</p>
                                    <ul className="mt-4 grid grid-cols-2 gap-2 font-mono text-sm text-amber-950 dark:text-amber-200 sm:grid-cols-3">
                                        {recoveryCodes.map((code) => <li className="rounded-lg border border-amber-200 bg-white px-3 py-2 text-center dark:border-amber-800 dark:bg-slate-900" key={code}>{code}</li>)}
                                    </ul>
                                </section>
                            )}

                            <section aria-labelledby="regenerate-heading" className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-6">
                                <h2 className="text-lg font-semibold text-slate-900 dark:text-slate-100" id="regenerate-heading">Regenerate recovery codes</h2>
                                <p className="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">Generating new codes immediately invalidates every previous recovery code.</p>
                                <form className="mt-5 grid gap-4" onSubmit={regenerateCodes}>
                                    <FormField autoComplete="current-password" error={recoveryForm.errors.current_password} id="recovery_current_password" label="Current password" onChange={(event) => recoveryForm.setData('current_password', event.target.value)} required type="password" value={recoveryForm.data.current_password} />
                                    <FormField autoComplete="one-time-code" error={recoveryForm.errors.code} id="recovery_code" label="Authenticator or recovery code" onChange={(event) => recoveryForm.setData('code', event.target.value)} required value={recoveryForm.data.code} />
                                    <div><Button disabled={recoveryForm.processing} type="submit">{recoveryForm.processing ? 'Regenerating…' : 'Regenerate codes'}</Button></div>
                                </form>
                            </section>

                            <section aria-labelledby="disable-heading" className="rounded-2xl border border-red-200 bg-white p-4 shadow-sm dark:border-red-900 dark:bg-slate-900 sm:p-6">
                                <h2 className="text-lg font-semibold text-slate-900 dark:text-slate-100" id="disable-heading">Disable authenticator</h2>
                                <p className="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">Disabling removes the saved authenticator and recovery codes. Confirm your password and a current authenticator or unused recovery code.</p>
                                <form className="mt-5 grid gap-4" onSubmit={disableMfa}>
                                    <FormField autoComplete="current-password" error={disableForm.errors.current_password} id="disable_current_password" label="Current password" onChange={(event) => disableForm.setData('current_password', event.target.value)} required type="password" value={disableForm.data.current_password} />
                                    <FormField autoComplete="one-time-code" error={disableForm.errors.code} id="disable_code" label="Authenticator or recovery code" onChange={(event) => disableForm.setData('code', event.target.value)} required value={disableForm.data.code} />
                                    <div><Button disabled={disableForm.processing} type="submit" variant="danger">{disableForm.processing ? 'Disabling…' : 'Disable two-factor authentication'}</Button></div>
                                </form>
                            </section>
                        </div>
                    )}
                </section>
            </AuthenticatedLayout>
        </>
    );
}
