<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => fn () => $request->user() ? [
                    'id' => $request->user()->id,
                    'first_name' => $request->user()->first_name,
                    'display_name' => $request->user()->display_name,
                    'email' => $request->user()->email,
                    'can_manage_users' => $request->user()->can('users.view'),
                    'can_create_users' => $request->user()->can('users.create'),
                    'can_update_users' => $request->user()->can('users.update'),
                    'can_view_roles' => $request->user()->can('roles.view'),
                    'can_manage_roles' => $request->user()->can('roles.manage'),
                    'can_view_permissions' => $request->user()->can('permissions.view'),
                    'can_view_activity_logs' => $request->user()->can('activity-logs.view'),
                    'can_view_contacts' => config('demo.modules_enabled') && config('demo.contact_directory_enabled') && $request->user()->can('contacts.view'),
                    'can_view_articles' => config('demo.modules_enabled') && config('demo.blog_enabled') && $request->user()->can('articles.view'),
                    'can_view_community_feed' => config('demo.modules_enabled') && config('demo.community_feed_enabled') && $request->user()->can('community-feed.view'),
                    'can_update_contacts' => config('demo.modules_enabled') && config('demo.contact_directory_enabled') && $request->user()->can('contacts.update'),
                    'can_archive_contacts' => config('demo.modules_enabled') && config('demo.contact_directory_enabled') && $request->user()->can('contacts.archive'),
                    'can_delete_contacts' => config('demo.modules_enabled') && config('demo.contact_directory_enabled') && $request->user()->can('contacts.delete'),
                    'can_view_contact_trash' => config('demo.modules_enabled') && config('demo.contact_directory_enabled') && $request->user()->can('contacts.view_trash'),
                    'can_restore_contacts' => config('demo.modules_enabled') && config('demo.contact_directory_enabled') && $request->user()->can('contacts.restore'),
                    'can_force_delete_contacts' => config('demo.modules_enabled') && config('demo.contact_directory_enabled') && $request->user()->can('contacts.force-delete'),
                    'can_manage_permissions' => $request->user()->can('permissions.manage'),
                ] : null,
            ],
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
            ],
        ];
    }
}
