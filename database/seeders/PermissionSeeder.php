<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /** @var list<string> */
    private const PERMISSIONS = [
        'users.view',
        'users.create',
        'users.update',
        'users.delete',
        'users.manage',
        'roles.view',
        'roles.manage',
        'permissions.view',
        'permissions.manage',
        'activity-logs.view',
        'contacts.view',
        'contacts.create',
        'contacts.update',
        'contacts.archive',
        'contacts.delete',
        'contacts.view_trash',
        'contacts.restore',
        'contacts.force-delete',
        'articles.view',
        'articles.view_history',
        'articles.create',
        'articles.update',
        'articles.preview',
        'articles.publish',
        'articles.unpublish',
        'articles.archive',
        'articles.delete',
        'articles.view_trash',
        'articles.restore',
        'articles.force-delete',
        'community-feed.view',
        'community-feed.create',
        'community-feed.update',
        'community-feed.delete',
        'community-feed.archive',
        'community-feed.view_trash',
        'community-feed.restore',
        'community-feed.force-delete',
        'community-feed.comment',
        'community-feed.update_comment',
        'community-feed.delete_comment',
        'community-feed.reply',
        'community-feed.like',
        'community-feed.moderate_posts',
        'community-feed.moderate_comments',
    ];

    public function run(PermissionRegistrar $permissionRegistrar): void
    {
        $permissionRegistrar->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $permissionRegistrar->forgetCachedPermissions();
    }
}
