<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function index(): Response
    {
        $permissions = Permission::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->groupBy(fn (Permission $permission): string => str_contains($permission->name, '.')
                ? str($permission->name)->before('.')->toString()
                : 'other')
            ->map(fn ($items) => $items->map(fn (Permission $permission): array => [
                'id' => $permission->id,
                'name' => $permission->name,
            ])->values())
            ->all();

        return Inertia::render('Permissions/Index', ['groups' => $permissions]);
    }
}
