<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class CreateAdministrator extends Command
{
    protected $signature = 'users:create-admin';

    protected $description = 'Create the first administrator account';

    public function handle(): int
    {
        $data = Validator::make([
            'first_name' => trim($this->ask('First name') ?? ''),
            'last_name' => trim($this->ask('Last name') ?? ''),
            'email' => mb_strtolower(trim($this->ask('Email address') ?? '')),
            'password' => $this->secret('Password'),
            'password_confirmation' => $this->secret('Confirm password'),
        ], [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ])->validate();

        $user = DB::transaction(function () use ($data): User {
            $user = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);
            $user->assignRole(Role::findByName('administrator', 'web'));

            return $user;
        });

        $user->sendEmailVerificationNotification();
        $this->info('Administrator account created. Verify its email before signing in.');

        return self::SUCCESS;
    }
}
