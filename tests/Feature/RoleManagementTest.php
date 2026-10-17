<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AuditLogger;
use Database\Seeders\AdministratorRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_and_regular_user_cannot_view_or_manage_roles_and_permissions(): void
    {
        $this->get(route('roles.index'))->assertRedirect(route('login'));
        $this->get(route('permissions.index'))->assertRedirect(route('login'));

        $user = User::factory()->create();
        $this->actingAs($user)
            ->get(route('roles.index'))
            ->assertForbidden();
        $this->get(route('permissions.index'))->assertForbidden();
        $this->post(route('roles.store'), ['name' => 'Support', 'permission_ids' => []])->assertForbidden();
        $this->put(route('users.roles.update', $user), ['role_ids' => []])->assertForbidden();
    }

    public function test_administrator_can_list_permissions_create_roles_and_update_role_permissions(): void
    {
        $admin = $this->makeAdministrator(User::factory()->create());
        $viewUsers = Permission::findByName('users.view', 'web');
        $createUsers = Permission::findByName('users.create', 'web');

        $this->actingAs($admin)
            ->get(route('roles.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Roles/Index')
                ->where('can_manage_roles', true)
                ->has('roles'));

        $this->get(route('permissions.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Permissions/Index')
                ->has('groups.users')
                ->where('groups.users.0.name', 'users.create'));

        $this->post(route('roles.store'), [
            'name' => 'Support Agent',
            'permission_ids' => [$viewUsers->id],
        ])->assertRedirect(route('roles.index'));

        $role = Role::findByName('Support Agent', 'web');
        $this->assertTrue($role->hasPermissionTo('users.view'));
        $this->assertFalse($role->hasPermissionTo('users.create'));

        $this->put(route('roles.update', $role), [
            'name' => 'Support Lead',
            'permission_ids' => [$viewUsers->id, $createUsers->id],
        ])->assertRedirect(route('roles.index'));

        $role->refresh();
        $this->assertSame('Support Lead', $role->name);
        $this->assertTrue($role->hasPermissionTo('users.create'));

        $this->assertSame(['role.created', 'role.updated'], Activity::query()
            ->whereIn('event', ['role.created', 'role.updated'])
            ->orderBy('id')
            ->pluck('event')
            ->all());
    }

    public function test_administrator_role_keeps_its_name_and_core_permissions(): void
    {
        $admin = $this->makeAdministrator(User::factory()->create());
        $role = Role::findByName('administrator', 'web');

        $this->actingAs($admin)
            ->put(route('roles.update', $role), ['name' => 'Renamed', 'permission_ids' => []])
            ->assertRedirect(route('roles.index'));

        $role->refresh();
        $this->assertSame('administrator', $role->name);
        $this->assertTrue($role->hasPermissionTo('roles.manage'));
        $this->assertTrue($role->hasPermissionTo('users.view'));
    }

    public function test_role_management_does_not_allow_permission_changes_without_permission_management_access(): void
    {
        $this->seed(AdministratorRoleSeeder::class);
        $roleManager = Role::create(['name' => 'role-manager', 'guard_name' => 'web']);
        $roleManager->givePermissionTo(['roles.view', 'roles.manage']);
        $user = User::factory()->create();
        $user->assignRole($roleManager);
        $this->assertTrue($user->can('roles.manage'));
        $this->assertFalse($user->can('permissions.manage'));
        $targetRole = Role::create(['name' => 'support', 'guard_name' => 'web']);
        $viewUsers = Permission::findByName('users.view', 'web');
        $createUsers = Permission::findByName('users.create', 'web');
        $targetRole->givePermissionTo($viewUsers);

        $this->actingAs($user)
            ->get(route('roles.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('can_manage_roles', true)
                ->where('auth.user.can_manage_permissions', false));

        $this->put(route('roles.update', $targetRole), [
            'name' => 'support-lead',
            'permission_ids' => [$viewUsers->id],
        ])->assertRedirect(route('roles.index'));

        $this->put(route('roles.update', $targetRole), [
            'name' => 'support-lead',
            'permission_ids' => [$viewUsers->id, $createUsers->id],
        ])->assertForbidden();

        $this->post(route('roles.store'), [
            'name' => 'unauthorized-grant',
            'permission_ids' => [$createUsers->id],
        ])->assertForbidden();

        $targetUser = User::factory()->create();
        $administrator = Role::findByName('administrator', 'web');
        $this->put(route('users.roles.update', $targetUser), ['role_ids' => [$administrator->id]])
            ->assertForbidden();

        $this->assertFalse($targetRole->fresh()->hasPermissionTo('users.create'));
        $this->assertFalse($targetUser->fresh()->hasRole('administrator'));
    }

    public function test_roles_can_be_assigned_to_users_but_the_last_active_administrator_is_protected(): void
    {
        $admin = $this->makeAdministrator(User::factory()->create());
        $target = User::factory()->create();
        $memberRole = Role::create(['name' => 'member', 'guard_name' => 'web']);

        $this->actingAs($admin)
            ->put(route('users.roles.update', $target), ['role_ids' => [$memberRole->id]])
            ->assertRedirect(route('users.show', $target));
        $this->assertTrue($target->fresh()->hasRole('member'));

        $this->put(route('users.roles.update', $admin), ['role_ids' => [$memberRole->id]])
            ->assertSessionHasErrors('role_ids');
        $this->assertTrue($admin->fresh()->hasRole('administrator'));

        $secondAdmin = $this->makeAdministrator(User::factory()->create());
        $this->put(route('users.roles.update', $admin), ['role_ids' => [$memberRole->id]])
            ->assertRedirect(route('users.show', $admin));

        $this->assertTrue($admin->fresh()->hasRole('member'));
        $this->assertTrue($secondAdmin->fresh()->hasRole('administrator'));
    }

    public function test_role_assignment_and_its_audit_record_share_a_transaction(): void
    {
        $admin = $this->makeAdministrator(User::factory()->create());
        $target = User::factory()->create();
        $role = Role::create(['name' => 'support', 'guard_name' => 'web']);
        $auditLogger = \Mockery::mock(AuditLogger::class);
        $auditLogger->shouldReceive('log')->once()->andReturnUsing(function (): void {
            $this->assertGreaterThan(0, DB::transactionLevel());
        });
        $this->app->instance(AuditLogger::class, $auditLogger);

        $this->actingAs($admin)
            ->put(route('users.roles.update', $target), ['role_ids' => [$role->id]])
            ->assertRedirect(route('users.show', $target));

        $this->assertTrue($target->fresh()->hasRole('support'));
    }
}
