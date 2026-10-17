<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AdministratorRoleSeeder extends Seeder
{
    public function run(PermissionRegistrar $permissionRegistrar): void
    {
        $this->call(PermissionSeeder::class);

        $permissionRegistrar->forgetCachedPermissions();

        $role = Role::findOrCreate('administrator', 'web');
        $role->syncPermissions(Permission::where('guard_name', 'web')->get());

        $permissionRegistrar->forgetCachedPermissions();
    }
}
