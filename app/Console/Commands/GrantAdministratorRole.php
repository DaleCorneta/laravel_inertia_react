<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

class GrantAdministratorRole extends Command
{
    protected $signature = 'users:grant-admin {email : Email address of an existing user}';

    protected $description = 'Grant the administrator role to an existing user';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if (! $user) {
            $this->error('No user exists with that email address.');

            return self::FAILURE;
        }

        $role = Role::findByName('administrator', 'web');
        $user->assignRole($role);

        $this->info('Administrator role granted.');

        return self::SUCCESS;
    }
}
