<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdministratorRoleSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_does_not_create_a_default_user(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseHas('roles', [
            'name' => 'administrator',
            'guard_name' => 'web',
        ]);
    }

    public function test_permission_seeder_is_idempotent_and_uses_the_web_guard(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->assertDatabaseCount('permissions', 45);

        $this->seed(PermissionSeeder::class);
        $this->assertDatabaseCount('permissions', 45);
        $this->assertDatabaseHas('permissions', [
            'name' => 'users.view',
            'guard_name' => 'web',
        ]);
        $this->assertDatabaseHas('permissions', [
            'name' => 'activity-logs.view',
            'guard_name' => 'web',
        ]);
        $this->assertDatabaseHas('permissions', [
            'name' => 'contacts.view',
            'guard_name' => 'web',
        ]);
        $this->assertDatabaseHas('permissions', [
            'name' => 'contacts.update',
            'guard_name' => 'web',
        ]);
        foreach (['articles.view', 'articles.view_history', 'articles.create', 'articles.update', 'articles.preview', 'articles.publish', 'articles.unpublish', 'articles.archive', 'articles.delete', 'articles.view_trash', 'articles.restore', 'articles.force-delete'] as $permission) {
            $this->assertDatabaseHas('permissions', ['name' => $permission, 'guard_name' => 'web']);
        }
        $this->assertDatabaseHas('permissions', ['name' => 'community-feed.view', 'guard_name' => 'web']);
        $this->assertDatabaseHas('permissions', ['name' => 'community-feed.create', 'guard_name' => 'web']);
        $this->assertDatabaseHas('permissions', ['name' => 'community-feed.update', 'guard_name' => 'web']);
        $this->assertDatabaseHas('permissions', ['name' => 'community-feed.delete', 'guard_name' => 'web']);
        $this->assertDatabaseHas('permissions', ['name' => 'community-feed.archive', 'guard_name' => 'web']);
        $this->assertDatabaseHas('permissions', ['name' => 'community-feed.view_trash', 'guard_name' => 'web']);
        $this->assertDatabaseHas('permissions', ['name' => 'community-feed.restore', 'guard_name' => 'web']);
        $this->assertDatabaseHas('permissions', ['name' => 'community-feed.force-delete', 'guard_name' => 'web']);
        $this->assertDatabaseHas('permissions', ['name' => 'community-feed.comment', 'guard_name' => 'web']);
        $this->assertDatabaseHas('permissions', ['name' => 'community-feed.update_comment', 'guard_name' => 'web']);
        $this->assertDatabaseHas('permissions', ['name' => 'community-feed.delete_comment', 'guard_name' => 'web']);
        $this->assertDatabaseHas('permissions', ['name' => 'community-feed.reply', 'guard_name' => 'web']);
        $this->assertDatabaseHas('permissions', ['name' => 'community-feed.like', 'guard_name' => 'web']);
        $this->assertDatabaseHas('permissions', ['name' => 'community-feed.moderate_posts', 'guard_name' => 'web']);
        $this->assertDatabaseHas('permissions', ['name' => 'community-feed.moderate_comments', 'guard_name' => 'web']);
        foreach (['contacts.archive', 'contacts.delete', 'contacts.view_trash', 'contacts.restore', 'contacts.force-delete'] as $permission) {
            $this->assertDatabaseHas('permissions', ['name' => $permission, 'guard_name' => 'web']);
        }
    }

    public function test_user_model_can_receive_a_role_and_authorize_a_seeded_permission(): void
    {
        $this->seed(PermissionSeeder::class);

        $user = User::factory()->create();
        $role = Role::create(['name' => 'member', 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::findByName('users.view', 'web'));
        $user->assignRole($role);

        $this->assertTrue($user->hasRole('member'));
        $this->assertTrue($user->can('users.view'));
        $this->assertFalse($user->can('users.manage'));
    }

    public function test_administrator_role_seeder_is_idempotent_and_grants_the_permission_catalog(): void
    {
        $this->seed(AdministratorRoleSeeder::class);
        $this->seed(AdministratorRoleSeeder::class);

        $this->assertDatabaseCount('roles', 1);
        $this->assertDatabaseCount('role_has_permissions', 45);

        $admin = User::factory()->create();
        $admin->assignRole('administrator');

        $this->assertTrue($admin->can('users.view'));
        $this->assertTrue($admin->can('users.create'));
        $this->assertTrue($admin->can('activity-logs.view'));
    }

    public function test_grant_admin_command_assigns_role_to_existing_user_only(): void
    {
        $this->seed(AdministratorRoleSeeder::class);
        $user = User::factory()->create(['email' => 'existing@example.test']);

        $this->artisan('users:grant-admin', ['email' => ' EXISTING@EXAMPLE.TEST '])
            ->expectsOutput('Administrator role granted.')
            ->assertSuccessful();

        $this->assertTrue($user->fresh()->hasRole('administrator'));

        $this->artisan('users:grant-admin', ['email' => 'missing@example.test'])
            ->expectsOutput('No user exists with that email address.')
            ->assertFailed();
    }

    public function test_create_admin_command_prompts_securely_and_sends_verification(): void
    {
        Notification::fake();
        $this->seed(AdministratorRoleSeeder::class);

        $this->artisan('users:create-admin')
            ->expectsQuestion('First name', 'Initial')
            ->expectsQuestion('Last name', 'Administrator')
            ->expectsQuestion('Email address', ' INITIAL@EXAMPLE.TEST ')
            ->expectsQuestion('Password', 'StrongTemporaryPass123!')
            ->expectsQuestion('Confirm password', 'StrongTemporaryPass123!')
            ->expectsOutput('Administrator account created. Verify its email before signing in.')
            ->assertSuccessful();

        $user = User::where('email', 'initial@example.test')->firstOrFail();
        $this->assertTrue($user->hasRole('administrator'));
        $this->assertNull($user->email_verified_at);
        $this->assertTrue($user->can('users.view'));
        Notification::assertSentTo($user, VerifyEmail::class);
    }
}
