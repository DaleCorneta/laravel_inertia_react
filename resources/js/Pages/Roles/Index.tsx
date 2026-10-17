import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import Button from '../../Components/Auth/Button';
import FormField from '../../Components/Auth/FormField';
import Icon from '../../Components/Icon';
import VisibleColumnSelector from '../../Components/Tables/VisibleColumnSelector';
import useCursorBatch from '../../Components/Tables/useCursorBatch';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';

type RoleSummary = { id: number; name: string; type: 'system' | 'custom'; permissions_count: number; users_count: number };
type RoleColumn = 'name' | 'type' | 'permissions_count' | 'users_count';
type SortColumn = 'name' | 'permissions_count' | 'users_count';
type PaginationLink = { url: string | null; label: string; active: boolean };
type RolesIndexProps = {
    auth: { user: { display_name: string; email: string; can_manage_users: boolean; can_view_roles: boolean; can_view_permissions: boolean } | null };
    roles: { data: RoleSummary[]; links: PaginationLink[]; from: number | null; to: number | null; total: number; has_more: boolean; next_cursor: string | null };
    filters: { search: string; per_page: number | 'all'; sort: SortColumn; direction: 'asc' | 'desc' };
    can_manage_roles: boolean;
    flash: { status?: string | null };
};

const roleColumns: { value: RoleColumn; label: string }[] = [
    { value: 'name', label: 'Role' },
    { value: 'type', label: 'Type' },
    { value: 'permissions_count', label: 'Permissions' },
    { value: 'users_count', label: 'Assigned users' },
];

function roleValue(role: RoleSummary, column: RoleColumn, canManageRoles: boolean) {
    if (column === 'name') {
        const content = <span className="break-words font-semibold capitalize text-slate-900 dark:text-slate-100">{role.name}</span>;
        return canManageRoles ? <Link className="hover:text-brand hover:underline" href={`/roles/${role.id}/edit`}>{content}</Link> : content;
    }
    if (column === 'type') {
        return <span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${role.type === 'system' ? 'bg-violet-50 text-violet-700 dark:bg-violet-950 dark:text-violet-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'}`}>
            {role.type === 'system' ? 'System role' : 'Custom role'}
        </span>;
    }
    return (column === 'permissions_count' ? role.permissions_count : role.users_count).toLocaleString();
}

export default function Index({ auth, roles, filters, can_manage_roles }: RolesIndexProps) {
    const [search, setSearch] = useState(filters.search);
    const [selectedColumns, setSelectedColumns] = useState<RoleColumn[]>(roleColumns.map(({ value }) => value));
    const allMode = filters.per_page === 'all';
    const filtersPending = search.trim() !== filters.search;
    const visibleColumns = roleColumns.filter(({ value }) => selectedColumns.includes(value));
    const batchUrl = `/roles/batches?${new URLSearchParams({ search: filters.search, sort: filters.sort, direction: filters.direction })}`;
    const { rows, sentinelRef, hasMore, loading, loadError, loadMore } = useCursorBatch<RoleSummary>({
        initialData: roles.data,
        initialHasMore: roles.has_more,
        initialNextCursor: roles.next_cursor,
        batchUrl,
        enabled: allMode,
        paused: filtersPending,
        errorMessage: 'Could not load more roles. Check your connection and try again.',
    });

    useEffect(() => {
        setSearch(filters.search);
    }, [filters.search]);

    useEffect(() => {
        if (!filtersPending) return;
        const timeout = window.setTimeout(() => {
            router.get('/roles', { ...filters, search, page: 1 }, { preserveScroll: true, preserveState: true, replace: true });
        }, 350);
        return () => window.clearTimeout(timeout);
    }, [filters.direction, filters.per_page, filters.search, filters.sort, filtersPending, search]);

    const changeSort = (sort: SortColumn) => {
        const direction = filters.sort === sort && filters.direction === 'asc' ? 'desc' : 'asc';
        router.get('/roles', { ...filters, search, sort, direction, page: 1 }, { preserveScroll: true, preserveState: true });
    };

    const changePageSize = (per_page: number | 'all') => {
        router.get('/roles', { ...filters, search, per_page, page: 1 }, { preserveScroll: true, preserveState: true });
    };

    const sortIndicator = (column: SortColumn) => filters.sort === column ? (filters.direction === 'asc' ? ' ↑' : ' ↓') : '';

    return (
        <>
            <Head title="Roles" />
            <AuthenticatedLayout user={auth.user}>
                <section className="mx-auto min-w-0 w-full max-w-[1600px] py-4 sm:py-6">
                    <div className="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                        <div className="min-w-0">
                            <p className="inline-flex items-center gap-2 rounded-full bg-brand/10 px-3 py-1.5 font-brand text-xs font-semibold uppercase tracking-[0.14em] text-brand">
                                <Icon className="size-4" name="roles" /> Access control
                            </p>
                            <h1 className="mt-3 font-display text-3xl font-semibold tracking-tight text-slate-950 dark:text-slate-100 sm:text-4xl">Roles</h1>
                            <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-600 dark:text-slate-300 sm:text-base">Review roles, assigned users, and permission counts for this workspace.</p>
                        </div>
                        <div className="flex flex-wrap items-center gap-2 sm:shrink-0">
                            <span className="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 text-sm font-medium text-slate-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300">
                                <span className="font-semibold text-slate-900 dark:text-slate-100">{roles.total.toLocaleString()}</span> {roles.total === 1 ? 'role' : 'roles'}
                            </span>
                            <Link className="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800" href="/permissions">
                                <Icon className="size-4" name="shield" /> Permission catalog
                            </Link>
                            {can_manage_roles && <Link className="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-brand px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-brand/90" href="/roles/create">
                                <span aria-hidden="true" className="text-lg leading-none">+</span> Create role
                            </Link>}
                        </div>
                    </div>


                    <div className="mt-6 grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(19rem,0.8fr)]">
                        <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-5">
                            <form className="flex flex-col gap-3 sm:flex-row sm:items-end" onSubmit={(event) => event.preventDefault()}>
                                <FormField autoComplete="off" className="bg-white dark:bg-slate-800" id="search-roles" label="Search roles" name="search" onChange={(event) => setSearch(event.target.value)} placeholder="Role name" value={search} />
                                <div className="flex flex-wrap items-center gap-3">
                                    <span aria-live="polite" className="min-w-32 text-sm text-slate-500 dark:text-slate-400">{filtersPending ? 'Updating results…' : 'Live search ready'}</span>
                                    {search && <Button className="px-3 py-2.5" onClick={() => setSearch('')} type="button" variant="secondary">Clear search</Button>}
                                </div>
                            </form>
                        </div>

                        <form action="/roles/export" className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-5" method="get">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h2 className="text-sm font-semibold text-slate-900 dark:text-slate-100">Visible table and export columns</h2>
                                    <p className="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">Changes update the table immediately. PDF supports up to 1,000 roles.</p>
                                </div>
                                <div className="flex shrink-0 gap-2">
                                    <Button disabled={selectedColumns.length === 0} type="submit" variant="secondary">CSV</Button>
                                    <Button disabled={selectedColumns.length === 0} formAction="/roles/export/xlsx" type="submit">XLSX</Button>
                                    <Button disabled={selectedColumns.length === 0} formAction="/roles/export/pdf" type="submit" variant="secondary">PDF</Button>
                                </div>
                            </div>
                            <VisibleColumnSelector
                                className="mt-4 grid grid-cols-2 gap-x-4 gap-y-2 sm:grid-cols-4"
                                columns={roleColumns}
                                legend="Columns to display and export"
                                onChange={setSelectedColumns}
                                selected={selectedColumns}
                            />
                            <input name="search" type="hidden" value={search} />
                            <input name="sort" type="hidden" value={filters.sort} />
                            <input name="direction" type="hidden" value={filters.direction} />
                        </form>
                    </div>

                    <section aria-label="Role results" className="mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 dark:border-slate-800 sm:px-5">
                            <div>
                                <h2 className="text-sm font-semibold text-slate-900 dark:text-slate-100">Matching roles</h2>
                                <p className="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{visibleColumns.length} {visibleColumns.length === 1 ? 'column' : 'columns'} visible</p>
                            </div>
                            <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">{roles.total.toLocaleString()} total</span>
                        </div>
                        <div className="hidden overflow-x-auto sm:block">
                            <table className="w-full min-w-full text-left text-sm text-slate-600 dark:text-slate-300">
                                <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                                    <tr>
                                        {visibleColumns.map(({ value, label }) => {
                                            const sortable = value === 'name' || value === 'permissions_count' || value === 'users_count';
                                            return <th aria-sort={filters.sort === value ? (filters.direction === 'asc' ? 'ascending' : 'descending') : undefined} className="px-3 py-3 font-semibold sm:px-4" key={value} scope="col">
                                                {sortable ? <button className="hover:text-slate-900 dark:hover:text-slate-100" onClick={() => changeSort(value)} type="button">{label}{sortIndicator(value)}</button> : label}
                                            </th>;
                                        })}
                                        {can_manage_roles && <th className="px-3 py-3 font-semibold sm:px-4" scope="col">Actions</th>}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                    {rows.map((role) => (
                                        <tr className="align-middle hover:bg-slate-50/70 dark:hover:bg-slate-800/60" key={role.id}>
                                            {visibleColumns.map(({ value }) => value === visibleColumns[0]?.value
                                                ? <th className="px-3 py-3 sm:px-4" key={value} scope="row">{roleValue(role, value, can_manage_roles)}</th>
                                                : <td className="px-3 py-3 sm:px-4" key={value}>{roleValue(role, value, can_manage_roles)}</td>)}
                                            {can_manage_roles && <td className="px-3 py-3 sm:px-4"><Link className="inline-flex min-h-9 items-center gap-1.5 rounded-lg px-2.5 text-sm font-semibold text-brand transition hover:bg-brand/5" href={`/roles/${role.id}/edit`}>Edit role <Icon className="size-4 -rotate-90" name="chevron" /></Link></td>}
                                        </tr>
                                    ))}
                                    {rows.length === 0 && <tr><td className="px-5 py-14 text-center text-slate-500 dark:text-slate-400" colSpan={visibleColumns.length + (can_manage_roles ? 1 : 0)}><p className="font-semibold text-slate-700 dark:text-slate-200">No roles found</p><p className="mt-1">Try another role name.</p></td></tr>}
                                </tbody>
                            </table>
                        </div>
                        <div className="sm:hidden">
                            <div className="flex items-end gap-3 border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                                <label className="min-w-0 flex-1 text-xs font-semibold text-slate-500 dark:text-slate-400" htmlFor="mobile-role-sort">Sort by
                                    <select className="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-normal text-slate-800 focus:border-brand focus:ring-brand dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100" id="mobile-role-sort" onChange={(event) => changeSort(event.target.value as SortColumn)} value={filters.sort}>
                                        <option value="name">Role</option><option value="permissions_count">Permissions</option><option value="users_count">Assigned users</option>
                                    </select>
                                </label>
                                <button className="shrink-0 rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800" onClick={() => changeSort(filters.sort)} type="button">{filters.direction === 'asc' ? 'Ascending ↑' : 'Descending ↓'}</button>
                            </div>
                            <ul className="divide-y divide-slate-100 dark:divide-slate-800">
                                {rows.map((role) => <li className="px-4 py-4" key={role.id}><dl className="grid gap-3 text-sm">
                                    {visibleColumns.map(({ value, label }) => <div className="grid grid-cols-[6.5rem_minmax(0,1fr)] items-start gap-2" key={value}><dt className="pt-1 text-xs font-medium text-slate-400">{label}</dt><dd className="min-w-0 text-slate-600 dark:text-slate-300">{roleValue(role, value, can_manage_roles)}</dd></div>)}
                                    {can_manage_roles && <div className="border-t border-slate-100 pt-2 dark:border-slate-800"><Link className="text-sm font-semibold text-brand hover:underline" href={`/roles/${role.id}/edit`}>Edit role</Link></div>}
                                </dl></li>)}
                                {rows.length === 0 && <li className="px-5 py-12 text-center text-sm text-slate-500 dark:text-slate-400">No roles found. Try another role name.</li>}
                            </ul>
                        </div>
                        <div className="flex flex-col gap-4 border-t border-slate-100 px-5 py-4 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex flex-wrap items-center gap-4 text-sm text-slate-500 dark:text-slate-400">
                                <span>{allMode ? `Loaded ${rows.length} of ${roles.total}` : `Showing ${roles.from ?? 0}–${roles.to ?? 0} of ${roles.total}`}</span>
                                <label className="flex items-center gap-2" htmlFor="roles-per-page">Rows
                                    <select className="rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-sm text-slate-700 focus:border-brand focus:ring-brand dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100" id="roles-per-page" onChange={(event) => changePageSize(event.target.value === 'all' ? 'all' : Number(event.target.value))} value={filters.per_page}>
                                        {[10, 25, 50, 100].map((size) => <option key={size} value={size}>{size}</option>)}<option value="all">All (load as needed)</option>
                                    </select>
                                </label>
                            </div>
                            {allMode ? <div className="flex flex-col items-start gap-3 sm:items-end">
                                {hasMore && <><div aria-live="polite" className="text-sm text-slate-500 dark:text-slate-400">{loading ? 'Loading more roles…' : loadError ? 'Loading failed. You can retry.' : 'More roles are available'}</div><Button disabled={loading} onClick={() => void loadMore()} type="button" variant="secondary">{loading ? 'Loading…' : loadError ? 'Retry loading roles' : 'Load more roles'}</Button><div aria-hidden="true" className="h-px w-full" ref={sentinelRef} /></>}
                                {!hasMore && rows.length > 0 && <p aria-live="polite" className="text-sm text-slate-500 dark:text-slate-400">All matching roles are loaded.</p>}
                            </div> : <nav aria-label="Role list pages" className="flex flex-wrap gap-1">
                                {roles.links.map((link, index) => link.url ? <Link aria-current={link.active ? 'page' : undefined} className={`rounded-lg px-3 py-2 text-sm font-medium ${link.active ? 'bg-brand text-white' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'}`} href={link.url} key={`${link.label}-${index}`} preserveScroll>{link.label.replaceAll('&laquo;', '«').replaceAll('&raquo;', '»')}</Link> : <span className="rounded-lg px-3 py-2 text-sm text-slate-300 dark:text-slate-600" key={`${link.label}-${index}`}>{link.label.replaceAll('&laquo;', '«').replaceAll('&raquo;', '»')}</span>)}
                            </nav>}
                        </div>
                    </section>
                    {auth.user?.can_manage_users && <p className="mt-5 text-sm text-slate-500 dark:text-slate-400">Assign these roles from a user’s profile or when creating a user. <Link className="font-semibold text-brand hover:underline" href="/users">Open user management</Link></p>}
                </section>
            </AuthenticatedLayout>
        </>
    );
}
