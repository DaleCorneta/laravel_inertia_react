import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import Button from '../../Components/Auth/Button';
import FormField from '../../Components/Auth/FormField';
import VisibleColumnSelector from '../../Components/Tables/VisibleColumnSelector';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout';

type ActivityRow = {
    id: number;
    action: string;
    actor: string;
    subject: string;
    changes: string[];
    occurred_at: string | null;
};

type PaginationLink = { url: string | null; label: string; active: boolean };

type ActivityLogIndexProps = {
    auth: {
        user: { display_name: string; email: string; can_manage_users: boolean; can_view_activity_logs: boolean } | null;
    };
    activities: {
        data: ActivityRow[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    events: string[];
    filters: { search: string; event: string; per_page: number };
};

function eventLabel(event: string) {
    return event.split(/[._]/).map((part) => part.charAt(0).toUpperCase() + part.slice(1)).join(' ');
}

const exportColumns = [
    { value: 'action', label: 'Action' },
    { value: 'actor', label: 'Performed by' },
    { value: 'subject', label: 'Subject' },
    { value: 'changes', label: 'Change details' },
    { value: 'occurred_at', label: 'Date and time' },
] as const;

type ActivityColumn = typeof exportColumns[number]['value'];

function activityValue(activity: ActivityRow, column: ActivityColumn) {
    if (column === 'changes') {
        return activity.changes.length > 0
            ? activity.changes.map((change) => <span className="mb-1 block" key={change}>{change}</span>)
            : <span className="text-slate-400 dark:text-slate-500">—</span>;
    }

    if (column === 'occurred_at') {
        return activity.occurred_at ? new Date(activity.occurred_at).toLocaleString() : '—';
    }

    return activity[column];
}

export default function Index({ auth, activities, events, filters }: ActivityLogIndexProps) {
    const [search, setSearch] = useState(filters.search);
    const [selectedEvent, setSelectedEvent] = useState(filters.event);
    const [selectedExportColumns, setSelectedExportColumns] = useState<ActivityColumn[]>(exportColumns.map(({ value }) => value));

    useEffect(() => {
        setSearch(filters.search);
        setSelectedEvent(filters.event);
    }, [filters.search, filters.event]);

    useEffect(() => {
        if (search.trim() === filters.search && selectedEvent === filters.event) return;

        const timeout = window.setTimeout(() => {
            router.get('/activity-logs', {
                search,
                event: selectedEvent,
                per_page: filters.per_page,
                page: 1,
            }, { preserveScroll: true, preserveState: true, replace: true });
        }, 350);

        return () => window.clearTimeout(timeout);
    }, [search, selectedEvent, filters.search, filters.event, filters.per_page]);

    const visibleColumns = exportColumns.filter(({ value }) => selectedExportColumns.includes(value));
    const filtersPending = search.trim() !== filters.search || selectedEvent !== filters.event;

    function changePageSize(per_page: number) {
        router.get('/activity-logs', { search, event: selectedEvent, per_page, page: 1 }, {
            preserveScroll: true,
            preserveState: true,
        });
    }

    return (
        <>
            <Head title="Activity logs" />
            <AuthenticatedLayout user={auth.user}>
                <section className="mx-auto min-w-0 w-full max-w-[1600px] py-4 sm:py-6">
                    <div className="flex flex-wrap items-end justify-between gap-5">
                        <div>
                            <p className="font-brand text-xs font-semibold uppercase tracking-[0.18em] text-brand">Workspace administration</p>
                            <h1 className="mt-3 font-display text-3xl font-semibold tracking-tight text-slate-950 dark:text-slate-100 sm:text-4xl">Activity logs</h1>
                            <p className="mt-3 max-w-2xl text-base leading-7 text-slate-600 dark:text-slate-300">Review account and administration events recorded in this workspace.</p>
                        </div>
                        <span className="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300">
                            {activities.total} {activities.total === 1 ? 'event' : 'events'}
                        </span>
                    </div>

                    <form className="mt-6 flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:flex-row sm:items-end sm:p-5" onSubmit={(event) => event.preventDefault()}>
                        <FormField
                            autoComplete="off"
                            className="bg-white dark:bg-slate-800"
                            id="search"
                            label="Search activity"
                            name="search"
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Action, actor, or subject"
                            value={search}
                        />
                        <label className="flex w-full flex-col gap-2 text-sm font-medium text-slate-700 dark:text-slate-200 sm:max-w-xs" htmlFor="event">
                            Action
                            <select
                                className="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-brand focus:ring-brand dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                                id="event"
                                onChange={(event) => setSelectedEvent(event.target.value)}
                                value={selectedEvent}
                            >
                                <option value="">All actions</option>
                                {events.map((event) => <option key={event} value={event}>{eventLabel(event)}</option>)}
                            </select>
                        </label>
                        <div className="flex flex-wrap items-center gap-3">
                            <span aria-live="polite" className="min-w-32 text-sm text-slate-500 dark:text-slate-400">{filtersPending ? 'Updating results…' : 'Live search ready'}</span>
                            {(search || selectedEvent) && (
                                <Button className="px-3 py-2.5" onClick={() => { setSearch(''); setSelectedEvent(''); }} type="button" variant="secondary">
                                    Clear filters
                                </Button>
                            )}
                        </div>
                    </form>

                    <form action="/activity-logs/export" className="mt-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-5" method="get">
                        <div className="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                            <VisibleColumnSelector
                                className="min-w-0 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5"
                                columns={exportColumns}
                                legend="Choose columns shown in the table and exports"
                                legendClassName="text-sm font-semibold text-slate-900 dark:text-slate-100"
                                onChange={setSelectedExportColumns}
                                selected={selectedExportColumns}
                            />
                            <div className="flex flex-wrap items-center gap-2">
                                <input name="search" type="hidden" value={search} />
                                <input name="event" type="hidden" value={selectedEvent} />
                                <Button disabled={selectedExportColumns.length === 0} type="submit">CSV</Button>
                                <Button disabled={selectedExportColumns.length === 0} formAction="/activity-logs/export/xlsx" type="submit" variant="secondary">XLSX</Button>
                                <Button disabled={selectedExportColumns.length === 0} formAction="/activity-logs/export/pdf" type="submit" variant="secondary">PDF</Button>
                            </div>
                        </div>
                        <p className="mt-3 text-xs text-slate-500 dark:text-slate-400">The same selected columns appear in the table and exports. Exports use the current filters; PDF supports up to 1,000 records.</p>
                    </form>

                    <section aria-label="Activity results" className="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 dark:border-slate-800 sm:px-5">
                            <div>
                                <h2 className="text-sm font-semibold text-slate-900 dark:text-slate-100">Matching activity</h2>
                                <p className="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{visibleColumns.length} {visibleColumns.length === 1 ? 'column' : 'columns'} visible</p>
                            </div>
                            <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">{activities.total.toLocaleString()} total</span>
                        </div>
                        <div className="hidden overflow-x-auto sm:block">
                            <table className="w-full min-w-full text-left text-sm text-slate-600 dark:text-slate-300">
                                <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                                    <tr>
                                        {visibleColumns.map(({ value, label }) => <th className="px-3 py-3 font-semibold sm:px-4" key={value} scope="col">{label}</th>)}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                    {activities.data.map((activity) => (
                                        <tr className="align-top hover:bg-slate-50/70 dark:hover:bg-slate-800/60" key={activity.id}>
                                            {visibleColumns.map(({ value }) => (
                                                value === visibleColumns[0]?.value
                                                    ? <th className="px-3 py-3 font-semibold text-slate-900 dark:text-slate-100 sm:px-4" key={value} scope="row">{activityValue(activity, value)}</th>
                                                    : <td className={`px-3 py-3 sm:px-4 ${value === 'changes' ? 'max-w-sm' : ''} ${value === 'occurred_at' ? 'whitespace-nowrap' : ''}`} key={value}>{activityValue(activity, value)}</td>
                                            ))}
                                        </tr>
                                    ))}
                                    {activities.data.length === 0 && (
                                        <tr>
                                            <td className="px-5 py-14 text-center text-slate-500 dark:text-slate-400" colSpan={visibleColumns.length}>
                                                <p className="font-semibold text-slate-700 dark:text-slate-200">No activity found</p>
                                                <p className="mt-1">Try another search or action filter.</p>
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <ul className="divide-y divide-slate-100 dark:divide-slate-800 sm:hidden">
                            {activities.data.map((activity) => (
                                <li className="space-y-3 p-4" key={activity.id}>
                                    <dl className="grid gap-2 text-sm">
                                        {visibleColumns.map(({ value, label }) => (
                                            <div className="grid grid-cols-[6.5rem_minmax(0,1fr)] gap-2" key={value}>
                                                <dt className="text-xs font-medium text-slate-400">{label}</dt>
                                                <dd className={`break-words text-slate-600 dark:text-slate-300 ${value === 'action' ? 'font-semibold text-slate-900 dark:text-slate-100' : ''}`}>{activityValue(activity, value)}</dd>
                                            </div>
                                        ))}
                                    </dl>
                                </li>
                            ))}
                            {activities.data.length === 0 && <li className="px-5 py-12 text-center text-sm text-slate-500 dark:text-slate-400">No activity found. Try another search or action filter.</li>}
                        </ul>

                        <div className="flex flex-col gap-4 border-t border-slate-100 px-5 py-4 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex flex-wrap items-center gap-4 text-sm text-slate-500 dark:text-slate-400">
                                <span>Showing {activities.from ?? 0}–{activities.to ?? 0} of {activities.total}</span>
                                <label className="flex items-center gap-2" htmlFor="per_page">
                                    Rows
                                    <select
                                        className="rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-sm text-slate-700 focus:border-brand focus:ring-brand dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                                        id="per_page"
                                        onChange={(event) => changePageSize(Number(event.target.value))}
                                        value={filters.per_page}
                                    >
                                        {[10, 25, 50, 100].map((size) => <option key={size} value={size}>{size}</option>)}
                                    </select>
                                </label>
                            </div>
                            <nav aria-label="Activity log pages" className="flex flex-wrap gap-1">
                                {activities.links.map((link, index) => link.url ? (
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
                                ))}
                            </nav>
                        </div>
                    </section>
                </section>
            </AuthenticatedLayout>
        </>
    );
}
