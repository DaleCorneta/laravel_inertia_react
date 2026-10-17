import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import Button from '../../Components/Auth/Button';
import FormField from '../../Components/Auth/FormField';
import VisibleColumnSelector from '../../Components/Tables/VisibleColumnSelector';
import useCursorBatch from '../../Components/Tables/useCursorBatch';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';

type UserRow = {
    id: number;
    full_name: string;
    email: string;
    email_verified: boolean;
    created_at: string | null;
};

type PaginationLink = { url: string | null; label: string; active: boolean };

type UsersIndexProps = {
    auth: {
        user: { display_name: string; email: string; can_manage_users: boolean; can_create_users: boolean } | null;
    };
    flash: { status?: string | null };
    users: {
        data: UserRow[];
        links?: PaginationLink[];
        current_page?: number;
        last_page?: number;
        from?: number | null;
        to?: number | null;
        total: number;
        has_more?: boolean;
        next_cursor?: string | null;
    };
    filters: {
        search: string;
        per_page: number | 'all';
        sort: 'name' | 'email' | 'created_at';
        direction: 'asc' | 'desc';
    };
};

type SortColumn = UsersIndexProps['filters']['sort'];
type ExportColumn = 'name' | 'email' | 'email_verified' | 'created_at';

const exportColumns: { value: ExportColumn; label: string }[] = [
    { value: 'name', label: 'Name' },
    { value: 'email', label: 'Email' },
    { value: 'email_verified', label: 'Email verified' },
    { value: 'created_at', label: 'Joined' },
];

function userColumnValue(user: UserRow, column: ExportColumn, mobile = false) {
    if (column === 'name') {
        return <Link className={`${mobile ? 'block break-words ' : ''}font-semibold text-slate-900 hover:text-brand hover:underline dark:text-slate-100`} href={`/users/${user.id}`}>{user.full_name}</Link>;
    }

    if (column === 'email') return <span className="break-all text-slate-600 dark:text-slate-300">{user.email}</span>;
    if (column === 'email_verified') {
        return <span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${user.email_verified ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-amber-50 text-amber-800 dark:bg-amber-950 dark:text-amber-300'}`}>
            {user.email_verified ? 'Verified' : 'Pending'}
        </span>;
    }

    return user.created_at ? new Date(user.created_at).toLocaleDateString() : '—';
}

export default function Index({ auth, users, filters }: UsersIndexProps) {
    const [search, setSearch] = useState(filters.search);
    const allMode = filters.per_page === 'all';
    const [selectedExportColumns, setSelectedExportColumns] = useState<ExportColumn[]>(exportColumns.map(({ value }) => value));
    const filtersPending = search.trim() !== filters.search;
    const batchUrl = `/users/batches?${new URLSearchParams({ per_page: 'all', search: filters.search, sort: filters.sort, direction: filters.direction })}`;
    const { rows, sentinelRef, hasMore, loading, loadError, loadMore } = useCursorBatch<UserRow>({
        initialData: users.data,
        initialHasMore: users.has_more ?? false,
        initialNextCursor: users.next_cursor ?? null,
        batchUrl,
        enabled: allMode,
        paused: filtersPending,
        errorMessage: 'Could not load more users. Check your connection and try again.',
    });

    useEffect(() => {
        setSearch(filters.search);
    }, [filters.search]);

    useEffect(() => {
        if (!filtersPending) return;

        const timeout = window.setTimeout(() => {
            router.get('/users', { ...filters, search, page: 1 }, {
                preserveScroll: true,
                preserveState: true,
                replace: true,
            });
        }, 350);

        return () => window.clearTimeout(timeout);
    }, [filtersPending, filters, search]);

    function clearSearch() {
        setSearch('');
    }

    function changeSort(sort: SortColumn) {
        const direction = filters.sort === sort && filters.direction === 'asc' ? 'desc' : 'asc';

        router.get('/users', { ...filters, search, sort, direction, page: 1 }, {
            preserveScroll: true,
            preserveState: true,
        });
    }

    function changePageSize(per_page: number | 'all') {
        router.get('/users', { ...filters, search, per_page, page: 1 }, {
            preserveScroll: true,
            preserveState: true,
        });
    }

    const sortIndicator = (column: SortColumn) => filters.sort === column ? (filters.direction === 'asc' ? ' ↑' : ' ↓') : '';
    const visibleColumns = exportColumns.filter(({ value }) => selectedExportColumns.includes(value));

    return (
        <>
            <Head title="Users" />
            <AuthenticatedLayout user={auth.user}>
                <section className="mx-auto min-w-0 w-full max-w-[1600px] py-4 sm:py-6">
                    <div className="flex flex-wrap items-end justify-between gap-5">
                        <div>
                            <p className="font-brand text-xs font-semibold uppercase tracking-[0.18em] text-brand">Workspace administration</p>
                            <h1 className="mt-3 font-display text-3xl font-semibold tracking-tight text-slate-950 dark:text-slate-100 sm:text-4xl">Users</h1>
                            <p className="mt-3 max-w-2xl text-base leading-7 text-slate-600 dark:text-slate-300">Browse and find accounts in this workspace.</p>
                        </div>
                        <div className="flex items-center gap-3">
                            <span className="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300">
                                {users.total} {users.total === 1 ? 'account' : 'accounts'}
                            </span>
                            {auth.user?.can_create_users && <Link className="inline-flex items-center justify-center rounded-lg bg-brand px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand/90" href="/users/create">Add user</Link>}
                        </div>
                    </div>


                    <div className="mt-6 grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(19rem,0.8fr)]">
                        <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-5">
                            <form className="flex flex-col gap-3 sm:flex-row sm:items-end" onSubmit={(event) => event.preventDefault()}>
                                <FormField
                                    autoComplete="off"
                                    className="bg-white dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:focus:bg-slate-800"
                                    id="search"
                                    label="Search users"
                                    name="search"
                                    onChange={(event) => setSearch(event.target.value)}
                                    placeholder="Name or email address"
                                    value={search}
                                />
                                <div className="flex flex-wrap items-center gap-3">
                                    <span aria-live="polite" className="min-w-32 text-sm text-slate-500 dark:text-slate-400">{filtersPending ? 'Updating results…' : 'Live search ready'}</span>
                                    {search && <Button className="px-3 py-2.5" onClick={clearSearch} type="button" variant="secondary">Clear search</Button>}
                                </div>
                            </form>
                        </div>

                        <form action="/users/export" className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-5" method="get">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h2 className="text-sm font-semibold text-slate-900 dark:text-slate-100">Visible table and export columns</h2>
                                    <p className="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">Changes update the table immediately. PDF supports up to 1,000 users.</p>
                                </div>
                                <div className="flex shrink-0 gap-2">
                                    <Button disabled={selectedExportColumns.length === 0} type="submit" variant="secondary">CSV</Button>
                                    <Button disabled={selectedExportColumns.length === 0} formAction="/users/export/xlsx" type="submit">XLSX</Button>
                                    <Button disabled={selectedExportColumns.length === 0} formAction="/users/export/pdf" type="submit" variant="secondary">PDF</Button>
                                </div>
                            </div>
                            <VisibleColumnSelector columns={exportColumns} legend="Columns to export" onChange={setSelectedExportColumns} selected={selectedExportColumns} />
                            <input name="search" type="hidden" value={search} />
                            <input name="sort" type="hidden" value={filters.sort} />
                            <input name="direction" type="hidden" value={filters.direction} />
                            <input name="per_page" type="hidden" value={filters.per_page} />
                        </form>
                    </div>

                    <section aria-label="User results" className="mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 dark:border-slate-800 sm:px-5">
                            <div>
                                <h2 className="text-sm font-semibold text-slate-900 dark:text-slate-100">Matching users</h2>
                                <p className="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{visibleColumns.length} {visibleColumns.length === 1 ? 'column' : 'columns'} visible</p>
                            </div>
                            <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">{users.total.toLocaleString()} total</span>
                        </div>
                        <div className="hidden overflow-x-auto sm:block">
                            <table className="w-full min-w-full text-left text-sm text-slate-600 dark:text-slate-300">
                                <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                                    <tr>
                                        {visibleColumns.map(({ value, label }) => {
                                            const sortable = value === 'name' || value === 'email' || value === 'created_at';
                                            return (
                                                <th aria-sort={sortable && filters.sort === value ? (filters.direction === 'asc' ? 'ascending' : 'descending') : undefined} className="px-3 py-3 font-semibold sm:px-4" key={value} scope="col">
                                                    {sortable
                                                        ? <button className="hover:text-slate-900 dark:hover:text-slate-100" onClick={() => changeSort(value as SortColumn)} type="button">{label}{sortIndicator(value as SortColumn)}</button>
                                                        : label}
                                                </th>
                                            );
                                        })}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                    {rows.map((user) => (
                                        <tr className="hover:bg-slate-50/70 dark:hover:bg-slate-800/60" key={user.id}>
                                            {visibleColumns.map(({ value }) => value === visibleColumns[0]?.value
                                                ? <th className="px-3 py-3 font-semibold text-slate-900 dark:text-slate-100 sm:px-4" key={value} scope="row">{userColumnValue(user, value)}</th>
                                                : <td className="px-3 py-3 sm:px-4" key={value}>{userColumnValue(user, value)}</td>)}
                                        </tr>
                                    ))}
                                    {rows.length === 0 && (
                                        <tr>
                                            <td className="px-5 py-12 text-center text-slate-500 dark:text-slate-400" colSpan={visibleColumns.length}>
                                                <p className="font-semibold text-slate-700 dark:text-slate-200">No users found</p>
                                                <p className="mt-1">Try another name or email address.</p>
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <div className="sm:hidden">
                            <div className="flex items-center gap-3 border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                                <label className="min-w-0 flex-1 text-xs font-semibold text-slate-500 dark:text-slate-400" htmlFor="mobile-user-sort">
                                    Sort by
                                    <select className="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-normal text-slate-800 focus:border-brand focus:ring-brand dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100" id="mobile-user-sort" onChange={(event) => changeSort(event.target.value as SortColumn)} value={filters.sort}>
                                        <option value="name">Name</option>
                                        <option value="email">Email</option>
                                        <option value="created_at">Joined date</option>
                                    </select>
                                </label>
                                <button className="mt-5 shrink-0 rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800" onClick={() => changeSort(filters.sort)} type="button">
                                    {filters.direction === 'asc' ? 'Ascending ↑' : 'Descending ↓'}
                                </button>
                            </div>
                            <ul className="divide-y divide-slate-100 dark:divide-slate-800">
                                {rows.map((user) => (
                                    <li className="px-4 py-4" key={user.id}>
                                        <dl className="grid gap-3 text-sm">
                                            {visibleColumns.map(({ value, label }) => (
                                                <div className="grid grid-cols-[6.5rem_minmax(0,1fr)] items-start gap-2" key={value}>
                                                    <dt className="pt-1 text-xs font-medium text-slate-400">{label}</dt>
                                                    <dd className="min-w-0 text-slate-600 dark:text-slate-300">{userColumnValue(user, value, true)}</dd>
                                                </div>
                                            ))}
                                        </dl>
                                    </li>
                                ))}
                                {rows.length === 0 && <li className="px-5 py-12 text-center text-sm text-slate-500 dark:text-slate-400">No users found. Try another name or email address.</li>}
                            </ul>
                        </div>

                        <div className="flex flex-col gap-4 border-t border-slate-100 px-5 py-4 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex flex-wrap items-center gap-4 text-sm text-slate-500 dark:text-slate-400">
                                <span>
                                    {allMode ? `Loaded ${rows.length} of ${users.total}` : `Showing ${users.from ?? 0}–${users.to ?? 0} of ${users.total}`}
                                </span>
                                <label className="flex items-center gap-2" htmlFor="per_page">
                                    Rows
                                    <select
                                        className="rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-sm text-slate-700 focus:border-brand focus:ring-brand dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                                        id="per_page"
                                        onChange={(event) => changePageSize(event.target.value === 'all' ? 'all' : Number(event.target.value))}
                                        value={filters.per_page}
                                    >
                                        {[10, 25, 50, 100].map((size) => <option key={size} value={size}>{size}</option>)}
                                        <option value="all">All (load as needed)</option>
                                    </select>
                                </label>
                            </div>
                            {allMode ? (
                                <div className="flex flex-col items-start gap-3 sm:items-end">
                                    {hasMore && (
                                        <>
                                            <div aria-live="polite" className="text-sm text-slate-500 dark:text-slate-400">
                                                {loading ? 'Loading more users…' : 'More users are available'}
                                            </div>
                                            <Button disabled={loading} onClick={() => void loadMore()} type="button" variant="secondary">
                                                {loading ? 'Loading…' : loadError ? 'Retry loading users' : 'Load more users'}
                                            </Button>
                                            <div aria-hidden="true" className="h-px w-full" ref={sentinelRef} />
                                        </>
                                    )}
                                    {!hasMore && rows.length > 0 && <p aria-live="polite" className="text-sm text-slate-500 dark:text-slate-400">All matching users are loaded.</p>}
                                </div>
                            ) : (
                                <nav aria-label="User list pages" className="flex flex-wrap gap-1">
                                    {(users.links ?? []).map((link, index) => (
                                        link.url ? (
                                            <Link
                                                aria-current={link.active ? 'page' : undefined}
                                                className={`rounded-lg px-3 py-2 text-sm font-medium ${link.active ? 'bg-brand text-white' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'}`}
                                                href={link.url}
                                                key={`${link.label}-${index}`}
                                                preserveScroll
                                            >
                                                {link.label.replaceAll('&laquo;', '«').replaceAll('&raquo;', '»')}
                                            </Link>
                                        ) : (
                                            <span className="rounded-lg px-3 py-2 text-sm text-slate-300 dark:text-slate-600" key={`${link.label}-${index}`}>
                                                {link.label.replaceAll('&laquo;', '«').replaceAll('&raquo;', '»')}
                                            </span>
                                        )
                                    ))}
                                </nav>
                            )}
                        </div>
                    </section>
                </section>
            </AuthenticatedLayout>
        </>
    );
}
