import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import Button from '../../Components/Auth/Button';
import FormField from '../../Components/Auth/FormField';
import ConfirmDialog from '../../Components/ConfirmDialog';
import VisibleColumnSelector from '../../Components/Tables/VisibleColumnSelector';
import useCursorBatch from '../../Components/Tables/useCursorBatch';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';

type ContactRow = { id: number; full_name: string; organization: string | null; title: string | null; email: string | null; phone: string | null; archived_at: string | null };
type PaginationLink = { url: string | null; label: string; active: boolean };
type ExportColumn = 'name' | 'organization' | 'title' | 'email' | 'phone' | 'archived_at';
type SortColumn = 'name' | 'organization' | 'email' | 'created_at';
type ContactConfirmation = { id: number; action: 'trash' | 'delete' } | null;
const columns: { value: ExportColumn; label: string }[] = [
    { value: 'name', label: 'Name' }, { value: 'organization', label: 'Organization' }, { value: 'title', label: 'Title' },
    { value: 'email', label: 'Email' }, { value: 'phone', label: 'Phone' }, { value: 'archived_at', label: 'Archived' },
];

export default function Index({ auth, contacts, filters, flash, can_create_contacts, can_edit_contacts, can_archive_contacts, can_delete_contacts, can_view_trash, can_restore_contacts, can_force_delete_contacts }: {
    auth: { user: { display_name: string; email: string; can_manage_users: boolean } | null };
    flash: { status?: string | null };
    contacts: { data: ContactRow[]; links?: PaginationLink[]; from?: number | null; to?: number | null; total: number; has_more?: boolean; next_cursor?: string | null };
    filters: { search: string; view: 'active' | 'trash'; per_page: number | 'all'; sort: SortColumn; direction: 'asc' | 'desc' };
    can_create_contacts: boolean; can_edit_contacts: boolean; can_archive_contacts: boolean; can_delete_contacts: boolean;
    can_view_trash: boolean; can_restore_contacts: boolean; can_force_delete_contacts: boolean;
}) {
    const [search, setSearch] = useState(filters.search);
    const [confirmation, setConfirmation] = useState<ContactConfirmation>(null);
    const [selectedColumns, setSelectedColumns] = useState<ExportColumn[]>(columns.map(({ value }) => value));
    const allMode = filters.per_page === 'all';
    const filtersPending = search.trim() !== filters.search;
    const visibleColumns = columns.filter(({ value }) => selectedColumns.includes(value));
    const showActions = filters.view === 'trash' ? can_restore_contacts || can_force_delete_contacts : can_edit_contacts || can_archive_contacts || can_delete_contacts;
    const batchUrl = `/contacts/batches?${new URLSearchParams({ search: filters.search, view: filters.view, sort: filters.sort, direction: filters.direction })}`;
    const { rows, sentinelRef, hasMore, loading, loadError, loadMore } = useCursorBatch<ContactRow>({
        initialData: contacts.data,
        initialHasMore: contacts.has_more ?? false,
        initialNextCursor: contacts.next_cursor ?? null,
        batchUrl,
        enabled: allMode,
        paused: filtersPending,
        errorMessage: 'Could not load more contacts. Check your connection and try again.',
    });

    useEffect(() => setSearch(filters.search), [filters.search]);
    useEffect(() => {
        if (!filtersPending) return;
        const timeout = window.setTimeout(() => router.get('/contacts', { ...filters, search, page: 1 }, { preserveScroll: true, preserveState: true, replace: true }), 350);
        return () => window.clearTimeout(timeout);
    }, [filtersPending, filters, search]);

    const changeSort = (sort: SortColumn) => router.get('/contacts', { ...filters, search, sort, direction: filters.sort === sort && filters.direction === 'asc' ? 'desc' : 'asc', page: 1 }, { preserveScroll: true, preserveState: true });
    const changePageSize = (per_page: number | 'all') => router.get('/contacts', { ...filters, search, per_page, page: 1 }, { preserveScroll: true, preserveState: true });
    const sortIndicator = (column: SortColumn) => filters.sort === column ? (filters.direction === 'asc' ? ' ↑' : ' ↓') : '';
    const cell = (contact: ContactRow, column: ExportColumn) => {
        if (column === 'name') return can_edit_contacts && filters.view === 'active' ? <Link className="font-semibold text-slate-900 dark:text-slate-100 hover:text-brand hover:underline" href={`/contacts/${contact.id}/edit`}>{contact.full_name}</Link> : <span className="font-semibold text-slate-900 dark:text-slate-100">{contact.full_name}</span>;
        if (column === 'archived_at') return filters.view === 'active' && contact.archived_at ? <span className="inline-flex rounded-full bg-amber-50 dark:bg-amber-950 px-2.5 py-1 text-xs font-semibold text-amber-800 dark:text-amber-300">Archived</span> : '—';
        return contact[column] || '—';
    };
    const moveToTrash = (id: number) => setConfirmation({ id, action: 'trash' });
    const permanentlyDelete = (id: number) => setConfirmation({ id, action: 'delete' });

    return <>
        <Head title="Contacts" />
        <AuthenticatedLayout user={auth.user}>
            <section className="mx-auto min-w-0 w-full max-w-[1600px] py-4 sm:py-6">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div><p className="font-brand text-xs font-semibold uppercase tracking-[0.18em] text-brand">Directory</p><h1 className="mt-2 font-display text-3xl font-semibold tracking-tight text-slate-950 dark:text-slate-100">Contacts</h1><p className="mt-2 text-sm text-slate-600 dark:text-slate-300">Keep your business contacts organized.</p></div>
                    <div className="flex items-center gap-3"><span className="rounded-full border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-1.5 text-sm font-medium text-slate-600 dark:text-slate-300">{contacts.total.toLocaleString()} {contacts.total === 1 ? 'contact' : 'contacts'}</span>{can_create_contacts && <Link className="inline-flex items-center justify-center rounded-lg bg-brand px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand/90" href="/contacts/create">Add contact</Link>}</div>
                </div>
                <nav className="mt-5 flex flex-wrap gap-2" aria-label="Contact views">
                    <Link aria-current={filters.view === 'active' ? 'page' : undefined} className={`rounded-lg px-4 py-2 text-sm font-semibold ${filters.view === 'active' ? 'bg-brand/10 text-brand' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'}`} href="/contacts">Contacts</Link>
                    {can_view_trash && <Link aria-current={filters.view === 'trash' ? 'page' : undefined} className={`rounded-lg px-4 py-2 text-sm font-semibold ${filters.view === 'trash' ? 'bg-brand/10 text-brand' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'}`} href="/contacts?view=trash">Trash</Link>}
                </nav>
                <div className="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(20rem,0.8fr)]">
                    <div className="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-sm sm:p-5">
                        <FormField autoComplete="off" id="contact-search" label="Search contacts" onChange={(event) => setSearch(event.target.value)} placeholder="Name, organization, email, or phone" value={search} />
                        <p aria-live="polite" className="mt-2 text-xs text-slate-500 dark:text-slate-400">{filtersPending ? 'Updating results…' : 'Results update as you type.'}</p>
                    </div>
                    <form action="/contacts/export" className="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-sm sm:p-5" method="get">
                        <div className="flex flex-wrap items-start justify-between gap-3"><div><h2 className="text-sm font-semibold text-slate-900 dark:text-slate-100">Table and export columns</h2><p className="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">Choose fields to show and export. PDF supports up to 1,000 contacts.</p></div><div className="flex gap-2"><Button disabled={!selectedColumns.length} type="submit" variant="secondary">CSV</Button><Button disabled={!selectedColumns.length} formAction="/contacts/export/xlsx" type="submit">XLSX</Button><Button disabled={!selectedColumns.length} formAction="/contacts/export/pdf" type="submit" variant="secondary">PDF</Button></div></div>
                        <VisibleColumnSelector
                            className="mt-4 grid grid-cols-2 gap-x-4 gap-y-2 sm:grid-cols-3"
                            columns={columns}
                            legend="Visible and export columns"
                            onChange={setSelectedColumns}
                            selected={selectedColumns}
                        />
                        <input name="search" type="hidden" value={search} /><input name="view" type="hidden" value={filters.view} /><input name="sort" type="hidden" value={filters.sort} /><input name="direction" type="hidden" value={filters.direction} />
                    </form>
                </div>
                <section aria-label="Contact results" className="mt-4 overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 dark:border-slate-800 px-4 py-3 sm:px-5"><div><h2 className="text-sm font-semibold text-slate-900 dark:text-slate-100">{filters.view === 'trash' ? 'Trash contacts' : 'Matching contacts'}</h2><p className="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{visibleColumns.length} {visibleColumns.length === 1 ? 'column' : 'columns'} visible</p></div><span className="rounded-full bg-slate-100 dark:bg-slate-800 px-3 py-1 text-xs font-semibold text-slate-600 dark:text-slate-300">{contacts.total.toLocaleString()} total</span></div>
                    <div className="hidden overflow-x-auto sm:block"><table className="w-full text-left text-sm text-slate-600 dark:text-slate-300"><caption className="sr-only">{filters.view === 'trash' ? 'Contacts in trash' : 'Contact directory results'}</caption><thead className="bg-slate-50 dark:bg-slate-800 text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400"><tr>{visibleColumns.map(({ value, label }) => { const sortable = value === 'name' || value === 'organization' || value === 'email'; return <th aria-sort={sortable && filters.sort === value ? (filters.direction === 'asc' ? 'ascending' : 'descending') : undefined} className="px-3 py-3 font-semibold sm:px-4" key={value} scope="col">{sortable ? <button className="hover:text-slate-900 dark:text-slate-100" onClick={() => changeSort(value)} type="button">{label}{sortIndicator(value)}</button> : label}</th>; })}{showActions && <th className="px-3 py-3 font-semibold sm:px-4" scope="col">Actions</th>}</tr></thead>
                        <tbody className="divide-y divide-slate-100 dark:divide-slate-800">{rows.map((contact) => <tr className="hover:bg-slate-50 dark:hover:bg-slate-800/70" key={contact.id}>{visibleColumns.map(({ value }, index) => <td className="px-3 py-3 sm:px-4" key={value}>{cell(contact, value)}</td>)}{showActions && <td className="px-3 py-3 sm:px-4"><div className="flex flex-wrap gap-2">{filters.view === 'trash' ? <>{can_restore_contacts && <button className="rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800" onClick={() => router.post(`/contacts/${contact.id}/restore`, {}, { preserveScroll: true })} type="button">Restore</button>}{can_force_delete_contacts && <button className="rounded-lg border border-red-200 dark:border-red-900 px-3 py-2 text-xs font-semibold text-red-700 dark:text-red-300 hover:bg-red-50 dark:hover:bg-red-950" onClick={() => permanentlyDelete(contact.id)} type="button">Delete permanently</button>}</> : <>{can_edit_contacts && <Link className="rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800" href={`/contacts/${contact.id}/edit`}>Edit</Link>}{can_archive_contacts && <button className="rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800" onClick={() => router.patch(`/contacts/${contact.id}/${contact.archived_at ? 'unarchive' : 'archive'}`, {}, { preserveScroll: true })} type="button">{contact.archived_at ? 'Unarchive' : 'Archive'}</button>}{can_delete_contacts && <button className="rounded-lg border border-red-200 dark:border-red-900 px-3 py-2 text-xs font-semibold text-red-700 dark:text-red-300 hover:bg-red-50 dark:hover:bg-red-950" onClick={() => moveToTrash(contact.id)} type="button">Move to trash</button>}</>}</div></td>}</tr>)}{rows.length === 0 && <tr><td className="px-4 py-12 text-center text-slate-500 dark:text-slate-400" colSpan={visibleColumns.length + Number(showActions)}>{filters.view === 'trash' ? 'Trash is empty.' : filters.search ? 'No contacts match your search.' : 'No contacts have been added yet.'}</td></tr>}</tbody>
                    </table></div>
                    <div className="sm:hidden"><div className="flex items-center gap-3 border-b border-slate-100 dark:border-slate-800 px-4 py-3"><label className="min-w-0 flex-1 text-xs font-semibold text-slate-500 dark:text-slate-400" htmlFor="mobile-contact-sort">Sort by<select className="mt-1 block w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-sm font-normal text-slate-800 dark:text-slate-200" id="mobile-contact-sort" onChange={(event) => changeSort(event.target.value as SortColumn)} value={filters.sort}><option value="name">Name</option><option value="organization">Organization</option><option value="email">Email</option><option value="created_at">Created date</option></select></label><button className="mt-5 shrink-0 rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm font-semibold text-slate-700 dark:text-slate-200" onClick={() => changeSort(filters.sort)} type="button">{filters.direction === 'asc' ? 'Ascending ↑' : 'Descending ↓'}</button></div><ul className="divide-y divide-slate-100 dark:divide-slate-800">{rows.map((contact) => <li className="px-4 py-4" key={contact.id}><dl className="grid gap-3 text-sm">{visibleColumns.map(({ value, label }) => <div className="grid grid-cols-[6.5rem_minmax(0,1fr)] items-start gap-2" key={value}><dt className="pt-1 text-xs font-medium text-slate-400 dark:text-slate-500">{label}</dt><dd className="min-w-0 break-words text-slate-600 dark:text-slate-300">{cell(contact, value)}</dd></div>)}</dl>{showActions && <div className="mt-4 flex flex-wrap gap-2">{filters.view === 'trash' ? <>{can_restore_contacts && <button className="rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-xs font-semibold" onClick={() => router.post(`/contacts/${contact.id}/restore`, {}, { preserveScroll: true })} type="button">Restore</button>}{can_force_delete_contacts && <button className="rounded-lg border border-red-200 dark:border-red-900 px-3 py-2 text-xs font-semibold text-red-700 dark:text-red-300" onClick={() => permanentlyDelete(contact.id)} type="button">Delete permanently</button>}</> : <>{can_edit_contacts && <Link className="rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-xs font-semibold" href={`/contacts/${contact.id}/edit`}>Edit</Link>}{can_archive_contacts && <button className="rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-xs font-semibold" onClick={() => router.patch(`/contacts/${contact.id}/${contact.archived_at ? 'unarchive' : 'archive'}`, {}, { preserveScroll: true })} type="button">{contact.archived_at ? 'Unarchive' : 'Archive'}</button>}{can_delete_contacts && <button className="rounded-lg border border-red-200 dark:border-red-900 px-3 py-2 text-xs font-semibold text-red-700 dark:text-red-300" onClick={() => moveToTrash(contact.id)} type="button">Move to trash</button>}</>}</div>}</li>)}{rows.length === 0 && <li className="px-5 py-12 text-center text-sm text-slate-500 dark:text-slate-400">{filters.view === 'trash' ? 'Trash is empty.' : filters.search ? 'No contacts match your search.' : 'No contacts have been added yet.'}</li>}</ul></div>
                    <div className="flex flex-col gap-4 border-t border-slate-100 dark:border-slate-800 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5"><div className="flex flex-wrap items-center gap-4 text-sm text-slate-500 dark:text-slate-400"><span>{allMode ? `Loaded ${rows.length} of ${contacts.total}` : `Showing ${contacts.from ?? 0}–${contacts.to ?? 0} of ${contacts.total}`}</span><label className="flex items-center gap-2" htmlFor="contact-per-page">Rows<select className="rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-2.5 py-1.5 text-sm" id="contact-per-page" onChange={(event) => changePageSize(event.target.value === 'all' ? 'all' : Number(event.target.value))} value={filters.per_page}>{[10, 25, 50, 100].map((size) => <option key={size} value={size}>{size}</option>)}<option value="all">All (load as needed)</option></select></label></div>
                        {allMode ? <div className="flex flex-col items-start gap-2 sm:items-end">{hasMore && <><p aria-live="polite" className="text-sm text-slate-500 dark:text-slate-400">{loading ? 'Loading more contacts…' : loadError ? 'Loading failed. You can retry.' : 'More contacts are available'}</p><Button disabled={loading} onClick={() => void loadMore()} type="button" variant="secondary">{loading ? 'Loading…' : loadError ? 'Retry loading contacts' : 'Load more contacts'}</Button><div aria-hidden="true" className="h-px w-full" ref={sentinelRef} /></>}{!hasMore && rows.length > 0 && <p aria-live="polite" className="text-sm text-slate-500 dark:text-slate-400">All matching contacts are loaded.</p>}</div> : <nav aria-label="Contact pages" className="flex flex-wrap gap-1">{(contacts.links ?? []).map((link, index) => link.url ? <Link aria-current={link.active ? 'page' : undefined} className={`rounded-lg px-3 py-2 text-sm font-medium ${link.active ? 'bg-brand text-white' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'}`} href={link.url} key={`${link.label}-${index}`} preserveScroll>{link.label.replaceAll('&laquo;', '«').replaceAll('&raquo;', '»')}</Link> : <span className="rounded-lg px-3 py-2 text-sm text-slate-300 dark:text-slate-600" key={`${link.label}-${index}`}>{link.label.replaceAll('&laquo;', '«').replaceAll('&raquo;', '»')}</span>)}</nav>}
                    </div>
                </section>
            </section>
        </AuthenticatedLayout>
        <ConfirmDialog
            cancelLabel="Cancel"
            confirmLabel={confirmation?.action === 'delete' ? 'Delete permanently' : 'Move to trash'}
            description={confirmation?.action === 'delete' ? 'This permanently deletes the contact and cannot be undone.' : 'This moves the contact to trash, where it can be restored later.'}
            onCancel={() => setConfirmation(null)}
            onConfirm={() => {
                if (confirmation) router.delete(confirmation.action === 'delete' ? `/contacts/${confirmation.id}/permanent` : `/contacts/${confirmation.id}`, { preserveScroll: true });
                setConfirmation(null);
            }}
            open={confirmation !== null}
            title={confirmation?.action === 'delete' ? 'Delete this contact permanently?' : 'Move this contact to trash?'}
        />
    </>;
}
