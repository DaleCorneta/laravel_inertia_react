import { Link, router, usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { useEffect, useState } from 'react';
import Icon from '../Components/Icon';
import type { IconName } from '../Components/Icon';
import FeedbackToasts from '../Components/FeedbackToasts';

type AuthenticatedLayoutProps = PropsWithChildren<{
    user: { display_name: string; email: string; can_manage_users: boolean; can_create_users?: boolean; can_view_roles?: boolean; can_manage_roles?: boolean; can_view_permissions?: boolean; can_manage_permissions?: boolean; can_view_activity_logs?: boolean; can_view_contacts?: boolean; can_view_articles?: boolean; can_view_community_feed?: boolean } | null;
}>;

type NavigationItem = { label: string; href: string; icon: IconName };
type Breadcrumb = { label: string; href?: string };

const navigation: NavigationItem[] = [
    { label: 'Dashboard', href: '/dashboard', icon: 'dashboard' },
    { label: 'Users', href: '/users', icon: 'users' },
    { label: 'Contacts', href: '/contacts', icon: 'users' },
    { label: 'Articles', href: '/articles', icon: 'article' },
    { label: 'Community', href: '/community', icon: 'community' },
    { label: 'Roles', href: '/roles', icon: 'roles' },
    { label: 'Permissions', href: '/permissions', icon: 'shield' },
    { label: 'Activity logs', href: '/activity-logs', icon: 'activity' },
    { label: 'Welcome page', href: '/', icon: 'home' },
    { label: 'Settings', href: '/settings', icon: 'settings' },
];

function getBreadcrumbs(url: string): Breadcrumb[] {
    const path = url.split('?')[0] ?? '/';

    if (path === '/dashboard') return [{ label: 'Dashboard' }];
    if (path === '/') return [{ label: 'Welcome page' }];
    if (path === '/profile') return [{ label: 'Dashboard', href: '/dashboard' }, { label: 'Profile' }];
    if (path === '/settings') return [{ label: 'Dashboard', href: '/dashboard' }, { label: 'Settings' }];
    if (path.startsWith('/contacts')) {
        const crumbs: Breadcrumb[] = [{ label: 'Dashboard', href: '/dashboard' }, { label: 'Contacts', href: '/contacts' }];
        if (path === '/contacts') return [...crumbs.slice(0, 1), { label: 'Contacts' }];
        return [...crumbs, { label: path.endsWith('/edit') ? 'Edit contact' : 'Add contact' }];
    }
    if (path.startsWith('/articles')) {
        const crumbs: Breadcrumb[] = [{ label: 'Dashboard', href: '/dashboard' }, { label: 'Articles', href: '/articles' }];
        if (path === '/articles') return [...crumbs.slice(0, 1), { label: 'Articles' }];
        if (path === '/articles/trash') return [...crumbs, { label: 'Trash' }];
        return [...crumbs, { label: path.endsWith('/edit') ? 'Edit article' : path.endsWith('/preview') ? 'Preview article' : 'Create article' }];
    }
    if (path === '/community/trash') return [{ label: 'Dashboard', href: '/dashboard' }, { label: 'Community', href: '/community' }, { label: 'Trash' }];
    if (/^\/community\/\d+\/comments$/.test(path)) return [{ label: 'Dashboard', href: '/dashboard' }, { label: 'Community', href: '/community' }, { label: 'Discussion' }];
    if (path === '/community' || path.startsWith('/community/')) return [{ label: 'Dashboard', href: '/dashboard' }, { label: 'Community' }];

    if (path.startsWith('/permissions')) return [{ label: 'Dashboard', href: '/dashboard' }, { label: 'Permissions' }];
    if (path.startsWith('/activity-logs')) return [{ label: 'Dashboard', href: '/dashboard' }, { label: 'Activity logs' }];

    if (path.startsWith('/roles')) {
        const crumbs: Breadcrumb[] = [{ label: 'Dashboard', href: '/dashboard' }, { label: 'Roles', href: '/roles' }];
        if (path === '/roles') return [...crumbs.slice(0, 1), { label: 'Roles' }];
        crumbs.push({ label: path.endsWith('/create') ? 'Create role' : 'Edit role' });
        return crumbs;
    }

    if (path.startsWith('/users')) {
        const crumbs: Breadcrumb[] = [{ label: 'Dashboard', href: '/dashboard' }, { label: 'Users', href: '/users' }];
        if (path === '/users') return [...crumbs.slice(0, 1), { label: 'Users' }];
        crumbs.push({ label: path.endsWith('/create') ? 'Add user' : path.endsWith('/edit') ? 'Edit user' : 'User details' });
        return crumbs;
    }

    return [{ label: 'Dashboard', href: '/dashboard' }];
}

function Sidebar({ collapsed, mobile = false, onNavigate }: { collapsed: boolean; mobile?: boolean; onNavigate?: () => void }) {
    const { url } = usePage();
    const { auth } = usePage<{ auth: { user: AuthenticatedLayoutProps['user'] } }>().props;
    const visibleNavigation = navigation.filter((item) => {
        if (item.href === '/users') return auth.user?.can_manage_users;
        if (item.href === '/contacts') return auth.user?.can_view_contacts;
        if (item.href === '/articles') return auth.user?.can_view_articles;
        if (item.href === '/community') return auth.user?.can_view_community_feed;
        if (item.href === '/roles') return auth.user?.can_view_roles;
        if (item.href === '/permissions') return auth.user?.can_view_permissions;
        if (item.href === '/activity-logs') return auth.user?.can_view_activity_logs;

        return true;
    });

    const isActive = (href: string) => (href === '/' ? url.split('?')[0] === '/' : url.startsWith(href));
    const compact = collapsed && !mobile;

    return (
        <aside className={`flex h-full flex-col border-r border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-950 ${mobile ? 'w-72' : `hidden lg:flex ${compact ? 'w-[5.25rem]' : 'w-72'} fixed inset-y-0 left-0 z-40 transition-[width] duration-200`}`}>
            <div className={`flex h-20 shrink-0 items-center border-b border-slate-100 dark:border-slate-800 ${compact ? 'justify-center px-2' : 'gap-3 px-6'}`}>
                <Link aria-label="Your Company home" className="flex min-w-0 items-center gap-3" href="/" onClick={onNavigate}>
                    <span aria-label="Company logo placeholder" className="grid size-10 shrink-0 place-items-center rounded-xl bg-brand text-[9px] font-bold tracking-wider text-white" role="img">LOGO</span>
                    {!compact && <span className="min-w-0">
                        <span className="block truncate font-brand text-sm font-bold tracking-wide text-slate-900 dark:text-slate-100">YOUR COMPANY</span>
                        <span className="mt-0.5 block truncate text-xs text-slate-500 dark:text-slate-400">Business workspace</span>
                    </span>}
                </Link>
            </div>

            <div className={`pt-7 ${compact ? 'px-2' : 'px-4'}`}>
                {!compact && <p className="px-3 text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">Workspace</p>}
                <nav aria-label="Main navigation" className={`space-y-1 ${compact ? '' : 'mt-3'}`}>
                    {visibleNavigation.map((item) => (
                        <Link
                            aria-current={isActive(item.href) ? 'page' : undefined}
                            aria-label={compact ? item.label : undefined}
                            className={`group flex items-center rounded-xl py-3 text-sm font-semibold transition focus:outline-none focus:ring-4 focus:ring-brand/15 ${compact ? 'justify-center px-3' : 'gap-3 px-3'} ${isActive(item.href) ? 'bg-brand/10 text-brand' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white'}`}
                            href={item.href}
                            key={item.href}
                            onClick={onNavigate}
                            title={compact ? item.label : undefined}
                        >
                            <Icon className="size-5 shrink-0" name={item.icon} />
                            {!compact && <span>{item.label}</span>}
                            {!compact && isActive(item.href) && <span aria-hidden="true" className="ml-auto size-1.5 rounded-full bg-brand" />}
                        </Link>
                    ))}
                </nav>
            </div>

            <div className={`mt-auto border-t border-slate-100 py-4 dark:border-slate-800 ${compact ? 'px-2' : 'px-4'}`}>
                <div className={`rounded-xl bg-slate-50 px-3 py-3 dark:bg-slate-900 ${compact ? 'flex justify-center' : ''}`}>
                    <p className={`text-xs leading-5 text-slate-500 dark:text-slate-400 ${compact ? 'sr-only' : ''}`}>A secure foundation for your team workspace.</p>
                    {compact && <span aria-label="Workspace" className="grid size-8 place-items-center rounded-full bg-brand/10 text-xs font-bold text-brand" role="img">W</span>}
                </div>
            </div>
        </aside>
    );
}

function HeaderMenu({ icon, label, children }: PropsWithChildren<{ icon: IconName; label: string }>) {
    return (
        <details className="group relative">
            <summary aria-label={label} className="flex size-10 cursor-pointer list-none items-center justify-center rounded-xl text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus:ring-4 focus:ring-slate-100 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white dark:focus:ring-slate-700 [&::-webkit-details-marker]:hidden">
                <Icon className="size-5" name={icon} />
            </summary>
            <div className="absolute right-0 z-50 mt-2 w-72 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg ring-1 ring-slate-900/5 dark:border-slate-700 dark:bg-slate-900 dark:ring-black/20">
                {children}
            </div>
        </details>
    );
}

export default function AuthenticatedLayout({ children, user }: AuthenticatedLayoutProps) {
    const { url } = usePage();
    const [collapsed, setCollapsed] = useState(false);
    const [mobileOpen, setMobileOpen] = useState(false);
    const [darkMode, setDarkMode] = useState(() => typeof document !== 'undefined' && document.documentElement.classList.contains('dark'));
    const crumbs = getBreadcrumbs(url);
    const initials = user?.display_name?.trim().slice(0, 1).toUpperCase() ?? '?';

    useEffect(() => {
        document.documentElement.classList.toggle('dark', darkMode);
        try {
            localStorage.setItem('theme', darkMode ? 'dark' : 'light');
        } catch {
            // Theme remains active for this page when browser storage is unavailable.
        }
    }, [darkMode]);

    return (
        <div className="min-h-screen bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
            <FeedbackToasts />
            {mobileOpen && <button aria-label="Close navigation" className="fixed inset-0 z-40 bg-slate-950/40 lg:hidden" onClick={() => setMobileOpen(false)} type="button" />}
            <Sidebar collapsed={collapsed} />
            {mobileOpen && <div className="fixed inset-y-0 left-0 z-50 h-dvh lg:hidden"><Sidebar collapsed={false} mobile onNavigate={() => setMobileOpen(false)} /></div>}

            <div className={`min-h-screen transition-[padding] duration-200 ${collapsed ? 'lg:pl-[5.25rem]' : 'lg:pl-72'}`}>
                <header className="sticky top-0 z-30 border-b border-slate-200 bg-white/95 shadow-sm shadow-slate-900/[0.02] backdrop-blur dark:border-slate-800 dark:bg-slate-900/95 dark:shadow-black/20">
                    <div className="flex h-16 items-center gap-2 px-3 sm:gap-3 sm:px-5 xl:px-7">
                        <button
                            aria-expanded={mobileOpen}
                            aria-label={mobileOpen ? 'Close sidebar' : collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
                            className="inline-flex size-10 shrink-0 items-center justify-center rounded-xl text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus:ring-4 focus:ring-slate-100 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white dark:focus:ring-slate-700"
                            onClick={() => {
                                if (window.matchMedia('(min-width: 1024px)').matches) setCollapsed((value) => !value);
                                else setMobileOpen((value) => !value);
                            }}
                            type="button"
                        >
                            <Icon className="size-5" name={mobileOpen ? 'close' : 'menu'} />
                        </button>

                        <nav aria-label="Breadcrumb" className="min-w-0 flex-1">
                            <ol className="flex min-w-0 items-center gap-2 overflow-hidden text-sm">
                                {crumbs.map((crumb, index) => (
                                    <li className="flex min-w-0 shrink-0 items-center gap-2" key={`${crumb.label}-${index}`}>
                                        {index > 0 && <span aria-hidden="true" className="text-slate-300">/</span>}
                                        {crumb.href ? <Link className="truncate font-medium text-slate-500 hover:text-brand dark:text-slate-400" href={crumb.href}>{crumb.label}</Link> : <span aria-current="page" className="truncate font-semibold text-slate-900 dark:text-slate-100">{crumb.label}</span>}
                                    </li>
                                ))}
                            </ol>
                        </nav>

                        <div className="flex shrink-0 items-center gap-1 sm:gap-2">
                            <button aria-label={darkMode ? 'Switch to light mode' : 'Switch to dark mode'} aria-pressed={darkMode} className="inline-flex size-10 items-center justify-center rounded-xl text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus:ring-4 focus:ring-slate-100 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white dark:focus:ring-slate-700" onClick={() => setDarkMode((value) => !value)} type="button">
                                <Icon className="size-5" name={darkMode ? 'sun' : 'moon'} />
                            </button>
                            <HeaderMenu icon="bell" label="Notifications">
                                <div className="border-b border-slate-100 px-4 py-3 dark:border-slate-800"><p className="text-sm font-semibold text-slate-900 dark:text-slate-100">Notifications</p></div>
                                <p className="px-4 py-5 text-sm leading-6 text-slate-500 dark:text-slate-400">You’re all caught up. New workspace notifications will appear here.</p>
                            </HeaderMenu>
                            <HeaderMenu icon="mail" label="Messages">
                                <div className="border-b border-slate-100 px-4 py-3 dark:border-slate-800"><p className="text-sm font-semibold text-slate-900 dark:text-slate-100">Messages</p></div>
                                <p className="px-4 py-5 text-sm leading-6 text-slate-500 dark:text-slate-400">Messaging is not enabled yet. This menu is ready for future workspace conversations.</p>
                            </HeaderMenu>

                            <details className="group relative ml-1">
                                <summary className="flex cursor-pointer list-none items-center gap-2 rounded-xl p-1.5 transition hover:bg-slate-100 focus:outline-none focus:ring-4 focus:ring-slate-100 dark:hover:bg-slate-800 dark:focus:ring-slate-700 [&::-webkit-details-marker]:hidden">
                                    <span aria-hidden="true" className="grid size-9 place-items-center rounded-full bg-brand/10 text-sm font-bold text-brand">{initials}</span>
                                    <span className="hidden max-w-36 text-left sm:block">
                                        <span className="block truncate text-sm font-semibold text-slate-800 dark:text-slate-100">{user?.display_name}</span>
                                        <span className="block truncate text-xs text-slate-500 dark:text-slate-400">Workspace account</span>
                                    </span>
                                    <Icon className="hidden size-4 text-slate-400 sm:block" name="chevron" />
                                </summary>
                                <div className="absolute right-0 z-50 mt-2 w-64 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg ring-1 ring-slate-900/5 dark:border-slate-700 dark:bg-slate-900 dark:ring-black/20">
                                    <div className="border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                                        <p className="truncate text-sm font-semibold text-slate-900 dark:text-slate-100">{user?.display_name}</p>
                                        <p className="mt-0.5 truncate text-xs text-slate-500 dark:text-slate-400">{user?.email}</p>
                                    </div>
                                    <div className="p-2">
                                        <Link className="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800" href="/profile">
                                            <Icon className="size-4 text-slate-400" name="user" /> Profile
                                        </Link>
                                        <Link className="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800" href="/settings">
                                            <Icon className="size-4 text-slate-400" name="settings" /> Settings
                                        </Link>
                                        <div className="my-1 border-t border-slate-100 dark:border-slate-800" />
                                        <button className="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-semibold text-red-700 hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-950/40" onClick={() => router.delete('/logout')} type="button">
                                            <Icon className="size-4" name="logout" /> Sign out
                                        </button>
                                    </div>
                                </div>
                            </details>
                        </div>
                    </div>
                </header>

                <main className="min-h-[calc(100vh-4rem)] w-full min-w-0 bg-slate-50 px-4 py-5 dark:bg-slate-950 sm:px-6 sm:py-6 xl:px-8">
                    {children}
                </main>
            </div>
        </div>
    );
}
