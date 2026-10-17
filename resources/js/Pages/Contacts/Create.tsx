import { Head } from '@inertiajs/react';
import ContactForm from '../../Components/Contacts/ContactForm';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';

export default function Create({ auth }: { auth: { user: { display_name: string; email: string; can_manage_users: boolean } | null } }) {
    return (
        <>
            <Head title="Add contact" />
            <AuthenticatedLayout user={auth.user}>
                <section className="mx-auto w-full max-w-[1100px] py-4 sm:py-6">
                    <p className="font-brand text-xs font-semibold uppercase tracking-[0.18em] text-brand">Directory</p>
                    <h1 className="mt-2 font-display text-3xl font-semibold tracking-tight text-slate-950 dark:text-slate-100">Add a contact</h1>
                    <p className="mt-2 text-sm text-slate-600 dark:text-slate-300">Add a person and their professional contact details.</p>
                    <ContactForm />
                </section>
            </AuthenticatedLayout>
        </>
    );
}
