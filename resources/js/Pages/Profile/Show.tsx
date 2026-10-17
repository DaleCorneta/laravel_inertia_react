import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Button from '../../Components/Auth/Button';
import FormField from '../../Components/Auth/FormField';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';

type Profile = {
    prefix: string | null;
    first_name: string;
    middle_name: string | null;
    last_name: string;
    suffix: string | null;
    email: string;
    email_verified: boolean;
    mfa_enabled: boolean;
    google_login_enabled: boolean;
    google_account_linked: boolean;
};

type ProfileProps = {
    auth: { user: { display_name: string; email: string; can_manage_users: boolean } | null };
    profile: Profile;
    flash: { status?: string | null };
};

export default function Show({ auth, profile }: ProfileProps) {
    const profileForm = useForm({
        prefix: profile.prefix ?? '',
        first_name: profile.first_name,
        middle_name: profile.middle_name ?? '',
        last_name: profile.last_name,
        suffix: profile.suffix ?? '',
        email: profile.email,
        current_password: '',
    });
    const passwordForm = useForm({ current_password: '', password: '', password_confirmation: '' });
    const googleLinkForm = useForm({ current_password: '' });

    const updateProfile = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        profileForm.put('/profile', { onSuccess: () => profileForm.reset('current_password') });
    };

    const updatePassword = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        passwordForm.put('/profile/password', { onSuccess: () => passwordForm.reset() });
    };

    const linkGoogleAccount = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        googleLinkForm.post('/profile/social/google/link', { onSuccess: () => googleLinkForm.reset() });
    };

    return (
        <>
            <Head title="Profile" />
            <AuthenticatedLayout user={auth.user}>
                <section className="mx-auto min-w-0 w-full max-w-[1600px] py-4 sm:py-6">
                    <div>
                        <p className="font-brand text-xs font-semibold uppercase tracking-[0.18em] text-brand">Your account</p>
                        <h1 className="mt-3 font-display text-3xl font-semibold tracking-tight text-slate-950 dark:text-slate-100 sm:text-4xl">Profile</h1>
                        <p className="mt-3 text-base leading-7 text-slate-600 dark:text-slate-300">Update your account information and keep your sign-in details secure.</p>
                    </div>


                    {profile.mfa_enabled && (
                        <section aria-labelledby="mfa-heading" className="mt-6 flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:flex-row sm:items-center sm:justify-between sm:p-6">
                            <div>
                                <h2 className="text-lg font-semibold text-slate-900 dark:text-slate-100" id="mfa-heading">Authenticator app</h2>
                                <p className="mt-1 text-sm leading-6 text-slate-600 dark:text-slate-300">Add an extra sign-in check and manage your recovery codes.</p>
                            </div>
                            <Link className="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-slate-100 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 dark:focus:ring-slate-800" href="/profile/mfa">Manage two-factor authentication</Link>
                        </section>
                    )}

                    {profile.google_login_enabled && (
                        <section aria-labelledby="google-account-heading" className="mt-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-6">
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <h2 className="text-lg font-semibold text-slate-900 dark:text-slate-100" id="google-account-heading">Google sign-in</h2>
                                    <p className="mt-1 text-sm leading-6 text-slate-600 dark:text-slate-300">Link a Google account to use it for future sign-ins.</p>
                                </div>
                                {profile.google_account_linked && <span className="w-fit rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">Connected</span>}
                            </div>
                            {!profile.google_account_linked && (
                                <form className="mt-4 flex flex-col gap-4 sm:flex-row sm:items-end" onSubmit={linkGoogleAccount}>
                                    <div className="w-full sm:max-w-sm">
                                        <FormField autoComplete="current-password" error={googleLinkForm.errors.current_password} id="google_link_current_password" label="Current password" onChange={(event) => googleLinkForm.setData('current_password', event.target.value)} required type="password" value={googleLinkForm.data.current_password} />
                                    </div>
                                    <Button disabled={googleLinkForm.processing} type="submit">{googleLinkForm.processing ? 'Connecting…' : 'Connect Google account'}</Button>
                                </form>
                            )}
                        </section>
                    )}

                    <section aria-labelledby="profile-heading" className="mt-6 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-6">
                        <div className="flex items-center gap-4 border-b border-slate-100 pb-6 dark:border-slate-800">
                            <span aria-hidden="true" className="grid size-14 place-items-center rounded-2xl bg-brand/10 font-display text-xl font-semibold text-brand">{auth.user?.display_name.slice(0, 1).toUpperCase()}</span>
                            <div className="min-w-0 flex-1">
                                <h2 className="truncate text-lg font-semibold text-slate-900 dark:text-slate-100" id="profile-heading">{auth.user?.display_name}</h2>
                                <p className="truncate text-sm text-slate-500 dark:text-slate-400">{auth.user?.email}</p>
                            </div>
                            <span className={`shrink-0 rounded-full px-3 py-1 text-xs font-semibold ${profile.email_verified ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-amber-50 text-amber-800 dark:bg-amber-950 dark:text-amber-300'}`}>
                                {profile.email_verified ? 'Email verified' : 'Verification required'}
                            </span>
                        </div>

                        <form className="mt-6" onSubmit={updateProfile}>
                            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                <FormField autoComplete="honorific-prefix" id="prefix" label="Prefix" maxLength={20} onChange={(event) => profileForm.setData('prefix', event.target.value)} value={profileForm.data.prefix} />
                                <FormField autoComplete="given-name" error={profileForm.errors.first_name} id="first_name" label="First name" maxLength={100} onChange={(event) => profileForm.setData('first_name', event.target.value)} required value={profileForm.data.first_name} />
                                <FormField autoComplete="additional-name" id="middle_name" label="Middle name" maxLength={100} onChange={(event) => profileForm.setData('middle_name', event.target.value)} value={profileForm.data.middle_name} />
                                <FormField autoComplete="family-name" error={profileForm.errors.last_name} id="last_name" label="Last name" maxLength={100} onChange={(event) => profileForm.setData('last_name', event.target.value)} required value={profileForm.data.last_name} />
                                <FormField autoComplete="honorific-suffix" id="suffix" label="Suffix" maxLength={20} onChange={(event) => profileForm.setData('suffix', event.target.value)} value={profileForm.data.suffix} />
                                <FormField autoComplete="email" error={profileForm.errors.email} id="email" label="Email address" onChange={(event) => profileForm.setData('email', event.target.value)} required type="email" value={profileForm.data.email} />
                                <div className="sm:col-span-2 xl:col-span-3">
                                    <FormField autoComplete="current-password" error={profileForm.errors.current_password} id="profile_current_password" label="Current password" onChange={(event) => profileForm.setData('current_password', event.target.value)} required type="password" value={profileForm.data.current_password} />
                                    <p className="mt-2 text-xs leading-5 text-slate-500 dark:text-slate-400">Confirm your current password before saving profile changes. Changing your email requires verification at the new address.</p>
                                </div>
                            </div>
                            <div className="mt-6 flex justify-end">
                                <Button disabled={profileForm.processing} type="submit">{profileForm.processing ? 'Saving…' : 'Save profile'}</Button>
                            </div>
                        </form>
                    </section>

                    <section aria-labelledby="password-heading" className="mt-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-6">
                        <div>
                            <h2 className="text-lg font-semibold text-slate-900 dark:text-slate-100" id="password-heading">Change password</h2>
                            <p className="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400">Use a new password you do not use for another account.</p>
                        </div>
                        <form className="mt-6 grid gap-5 sm:grid-cols-2" onSubmit={updatePassword}>
                            <FormField autoComplete="current-password" error={passwordForm.errors.current_password} id="password_current_password" label="Current password" onChange={(event) => passwordForm.setData('current_password', event.target.value)} required type="password" value={passwordForm.data.current_password} />
                            <div className="hidden sm:block" />
                            <FormField autoComplete="new-password" error={passwordForm.errors.password} id="password" label="New password" onChange={(event) => passwordForm.setData('password', event.target.value)} required type="password" value={passwordForm.data.password} />
                            <FormField autoComplete="new-password" error={passwordForm.errors.password_confirmation} id="password_confirmation" label="Confirm new password" onChange={(event) => passwordForm.setData('password_confirmation', event.target.value)} required type="password" value={passwordForm.data.password_confirmation} />
                            <div className="sm:col-span-2 flex justify-end">
                                <Button disabled={passwordForm.processing} type="submit">{passwordForm.processing ? 'Updating…' : 'Update password'}</Button>
                            </div>
                        </form>
                    </section>
                </section>
            </AuthenticatedLayout>
        </>
    );
}
