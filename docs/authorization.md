# Initial administrator setup

Public registration is disabled. On a fresh installation, create the permission catalog and administrator role, then create the first administrator from the server console:

```sh
php artisan migrate --force
php artisan db:seed --class=AdministratorRoleSeeder
php artisan users:create-admin
```

The command prompts for the administrator's name, email, and password. Password input is hidden. It sends an email verification link; the account must be verified before using authenticated pages. Configure Laravel's mail transport before running the command if the verification email must be delivered.

To grant the administrator role to an existing account later, run:

```sh
php artisan users:grant-admin person@example.com
```

The command requires an existing account. It does not create default credentials. The `administrator` role receives the seeded permission catalog and authorizes user management on the server. Administrators cannot deactivate themselves or another administrator.

## Managing access

Users with `roles.view` can open `/roles`; `permissions.view` grants access to `/permissions`. Users need `roles.manage` to create roles, rename roles, or assign roles to user accounts. Changing a role's permission assignments or granting the administrator role additionally requires `permissions.manage`. The administrator role name and its core access permissions are protected, and the last active administrator cannot be demoted.
