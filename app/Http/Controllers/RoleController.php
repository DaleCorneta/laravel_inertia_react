<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Services\AuditLogger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class RoleController extends Controller
{
    private const BATCH_SIZE = 25;

    private const PDF_ROW_LIMIT = 1000;

    private const EXPORT_COLUMNS = [
        'name' => 'Role',
        'type' => 'Type',
        'permissions_count' => 'Permissions',
        'users_count' => 'Assigned users',
    ];

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', Rule::in(['10', '25', '50', '100', 'all', 10, 25, 50, 100])],
            'sort' => ['nullable', Rule::in(['name', 'permissions_count', 'users_count'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'cursor' => ['nullable', 'string', 'max:2048'],
        ]);
        $filters = [
            'search' => trim($filters['search'] ?? ''),
            'per_page' => $filters['per_page'] ?? 25,
            'sort' => $filters['sort'] ?? 'name',
            'direction' => $filters['direction'] ?? 'asc',
        ];
        $query = $this->rolesQuery($filters);
        $allMode = $filters['per_page'] === 'all';
        $perPage = $allMode ? self::BATCH_SIZE : (int) $filters['per_page'];
        $paginator = $allMode
            ? $query->cursorPaginate(self::BATCH_SIZE)->through($this->formatRole(...))
            : $query->paginate($perPage)->withQueryString()->through($this->formatRole(...));

        return Inertia::render('Roles/Index', [
            'roles' => [
                'data' => $paginator->items(),
                'links' => $allMode ? [] : $paginator->linkCollection(),
                'from' => $allMode ? ($paginator->count() > 0 ? 1 : null) : $paginator->firstItem(),
                'to' => $allMode ? $paginator->count() : $paginator->lastItem(),
                'total' => $allMode ? $this->roleCount($filters['search']) : $paginator->total(),
                'has_more' => $allMode && $paginator->hasMorePages(),
                'next_cursor' => $allMode ? $paginator->nextCursor()?->encode() : null,
            ],
            'filters' => [...$filters, 'per_page' => $allMode ? 'all' : $perPage],
            'can_manage_roles' => request()->user()->can('roles.manage'),
        ]);
    }

    public function batch(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(['name', 'permissions_count', 'users_count'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'cursor' => ['nullable', 'string', 'max:2048'],
        ]);
        $paginator = $this->rolesQuery([
            'search' => trim($filters['search'] ?? ''),
            'sort' => $filters['sort'] ?? 'name',
            'direction' => $filters['direction'] ?? 'asc',
        ])->cursorPaginate(self::BATCH_SIZE, ['*'], 'cursor', $filters['cursor'] ?? null);

        return response()->json([
            'data' => array_map($this->formatRole(...), $paginator->items()),
            'has_more' => $paginator->hasMorePages(),
            'next_cursor' => $paginator->nextCursor()?->encode(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->validateExportFilters($request);
        $columns = $filters['columns'];
        $roles = $this->rolesQuery($filters);

        return response()->streamDownload(function () use ($roles, $columns): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, array_map(fn (string $column): string => self::EXPORT_COLUMNS[$column], $columns), ',', '"', '', "\r\n");
            foreach ($roles->cursor() as $role) {
                fputcsv($output, array_map($this->safeCsvCell(...), $this->exportValues($role, $columns)), ',', '"', '', "\r\n");
            }
            fclose($output);
        }, 'roles-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function exportXlsx(Request $request)
    {
        $filters = $this->validateExportFilters($request);
        $columns = $filters['columns'];
        $roles = $this->rolesQuery($filters);
        $path = tempnam(sys_get_temp_dir(), 'roles-xlsx-');
        abort_if($path === false, 500, 'Unable to prepare the export.');
        $writer = new XlsxWriter;

        try {
            $writer->openToFile($path);
            $writer->addRow(new Row(array_map(fn (string $column): StringCell => new StringCell(self::EXPORT_COLUMNS[$column]), $columns)));
            foreach ($roles->cursor() as $role) {
                $writer->addRow(new Row(array_map(static fn (string $value): StringCell => new StringCell($value), $this->exportValues($role, $columns))));
            }
            $writer->close();
        } catch (Throwable $exception) {
            try {
                $writer->close();
            } catch (Throwable) {
            }
            if (is_file($path)) {
                unlink($path);
            }
            throw $exception;
        }

        return response()->download($path, 'roles-'.now()->format('Ymd-His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function exportPdf(Request $request)
    {
        $filters = $this->validateExportFilters($request);
        $columns = $filters['columns'];
        $roles = $this->rolesQuery($filters);
        abort_if((clone $roles)->count() > self::PDF_ROW_LIMIT, 422, 'PDF exports are limited to 1,000 roles. Narrow your search and try again.');

        return Pdf::loadView('exports.roles', [
            'columns' => array_map(fn (string $column): string => self::EXPORT_COLUMNS[$column], $columns),
            'rows' => $roles->get()->map(fn (Role $role): array => $this->exportValues($role, $columns))->all(),
            'generatedAt' => now()->format('Y-m-d H:i:s'),
        ])->setPaper('a4', 'landscape')->download('roles-'.now()->format('Ymd-His').'.pdf');
    }

    private function rolesQuery(array $filters): Builder
    {
        return Role::query()->where('guard_name', 'web')->withCount(['permissions', 'users'])
            ->when(($filters['search'] ?? '') !== '', fn (Builder $query) => $query->where('name', 'like', '%'.$filters['search'].'%'))
            ->orderBy($filters['sort'] ?? 'name', $filters['direction'] ?? 'asc')
            ->orderBy('id');
    }

    private function roleCount(string $search): int
    {
        return Role::query()->where('guard_name', 'web')
            ->when($search !== '', fn (Builder $query) => $query->where('name', 'like', '%'.$search.'%'))
            ->count();
    }

    private function validateExportFilters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(['name', 'permissions_count', 'users_count'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'columns' => ['required', 'array', 'list', 'min:1', 'max:4'],
            'columns.*' => ['required', 'string', 'distinct:strict', Rule::in(array_keys(self::EXPORT_COLUMNS))],
        ]);
    }

    private function safeCsvCell(string $value): string
    {
        return preg_match('/^[\x00-\x20]*[=+@\-]/u', $value) === 1 ? "'".$value : $value;
    }

    private function formatRole(Role $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'type' => $role->name === 'administrator' ? 'system' : 'custom',
            'permissions_count' => $role->permissions_count,
            'users_count' => $role->users_count,
        ];
    }

    private function exportValues(Role $role, array $columns): array
    {
        $row = $this->formatRole($role);

        return array_map(static fn (string $column): string => match ($column) {
            'type' => $row['type'] === 'system' ? 'System role' : 'Custom role',
            default => (string) $row[$column],
        }, $columns);
    }

    public function create(): Response
    {
        return Inertia::render('Roles/Create', [
            'permissions' => $this->permissions(),
            'can_manage_permissions' => request()->user()->can('permissions.manage'),
        ]);
    }

    public function store(StoreRoleRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $data = $request->validated();
        abort_if(! empty($data['permission_ids']) && ! $request->user()->can('permissions.manage'), 403);

        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
        $role->syncPermissions($this->permissionIds($data['permission_ids'] ?? []));
        $auditLogger->log('role.created', $role, [
            'permissions_added' => $role->permissions()->pluck('permissions.id')->map(static fn ($id): int => (int) $id)->all(),
        ]);

        return to_route('roles.index')->with('status', 'Role created.');
    }

    public function edit(Role $role): Response
    {
        abort_unless($role->guard_name === 'web', 404);

        return Inertia::render('Roles/Edit', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permission_ids' => $role->permissions()->pluck('permissions.id')->all(),
                'is_system' => $role->name === 'administrator',
            ],
            'permissions' => $this->permissions(),
            'can_manage_permissions' => request()->user()->can('permissions.manage'),
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role, AuditLogger $auditLogger): RedirectResponse
    {
        $data = $request->validated();
        $originalName = $role->name;
        $currentPermissionIds = $role->permissions()->pluck('permissions.id')->map(fn ($id): int => (int) $id)->all();
        $requestedPermissionIds = array_map('intval', $data['permission_ids'] ?? $currentPermissionIds);

        if (array_diff($requestedPermissionIds, $currentPermissionIds) !== []
            || array_diff($currentPermissionIds, $requestedPermissionIds) !== []) {
            abort_unless($request->user()->can('permissions.manage'), 403);
        }

        if ($role->name === 'administrator') {
            $data['name'] = 'administrator';
            $required = Permission::query()
                ->where('guard_name', 'web')
                ->whereIn('name', ['users.view', 'users.create', 'users.update', 'roles.view', 'roles.manage', 'permissions.view', 'permissions.manage'])
                ->pluck('id')
                ->all();
            $data['permission_ids'] = array_values(array_unique([...$requestedPermissionIds, ...$required]));
        } else {
            $data['permission_ids'] = $requestedPermissionIds;
        }

        $role->update(['name' => $data['name']]);
        $role->syncPermissions($this->permissionIds($data['permission_ids']));

        $added = array_values(array_diff($requestedPermissionIds, $currentPermissionIds));
        $removed = array_values(array_diff($currentPermissionIds, $requestedPermissionIds));

        if ($role->name !== $originalName || $added !== [] || $removed !== []) {
            $auditLogger->log('role.updated', $role, [
                'fields' => $role->name !== $originalName ? ['name'] : [],
                'permissions_added' => $added,
                'permissions_removed' => $removed,
            ]);
        }

        return to_route('roles.index')->with('status', 'Role updated.');
    }

    /** @return list<array{id: int, name: string, group: string}> */
    private function permissions(): array
    {
        return Permission::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Permission $permission): array => [
                'id' => $permission->id,
                'name' => $permission->name,
                'group' => str_contains($permission->name, '.') ? str($permission->name)->before('.')->toString() : 'other',
            ])
            ->all();
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, Permission>
     */
    private function permissionIds(array $ids): Collection
    {
        return Permission::query()->where('guard_name', 'web')->whereIn('id', $ids)->get();
    }
}
