<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UserRoleController extends Controller
{
    public function update(Request $request, User $user, AuditLogger $auditLogger): RedirectResponse
    {
        $data = Validator::make($request->all(), [
            'role_ids' => ['present', 'array', 'max:100'],
            'role_ids.*' => ['required', 'integer', 'distinct', Rule::exists('roles', 'id')->where('guard_name', 'web')],
        ])->validate();

        $updated = DB::transaction(function () use ($request, $user, $data, $auditLogger): bool {
            Role::query()
                ->where('guard_name', 'web')
                ->where('name', 'administrator')
                ->lockForUpdate()
                ->first();

            $user->refresh();
            $roles = Role::query()->where('guard_name', 'web')->whereIn('id', $data['role_ids'])->get();
            $currentlyAdministrator = $user->hasRole('administrator');

            if (! $currentlyAdministrator
                && $roles->contains('name', 'administrator')
                && ! $request->user()->can('permissions.manage')) {
                abort(403);
            }

            if ($user->is_active && $currentlyAdministrator && ! $roles->contains('name', 'administrator')
                && User::role('administrator')->where('is_active', true)->count() <= 1) {
                return false;
            }

            $currentRoleIds = $user->roles()->pluck('roles.id')->map(static fn ($id): int => (int) $id)->all();
            $newRoleIds = $roles->modelKeys();
            $added = array_values(array_diff($newRoleIds, $currentRoleIds));
            $removed = array_values(array_diff($currentRoleIds, $newRoleIds));

            $user->syncRoles($roles);

            if ($added !== [] || $removed !== []) {
                $auditLogger->log('user.roles_updated', $user, [
                    'roles_added' => $added,
                    'roles_removed' => $removed,
                ]);
            }

            return true;
        });

        if (! $updated) {
            return back()->withErrors(['role_ids' => 'The only active administrator cannot lose the administrator role.']);
        }

        return to_route('users.show', $user)->with('status', 'User roles updated.');
    }
}
