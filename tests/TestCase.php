<?php

namespace Tests;

use App\Models\User;
use Database\Seeders\AdministratorRoleSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function makeAdministrator(User $user): User
    {
        $this->seed(AdministratorRoleSeeder::class);

        return tap($user)->assignRole('administrator');
    }
}
