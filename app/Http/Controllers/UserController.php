<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Requests\UpdateUserStatusRequest;
use App\Models\User;
use App\Services\AuditLogger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class UserController extends Controller
{
    private const BATCH_SIZE = 25;

    private const PDF_ROW_LIMIT = 1000;

    private const EXPORT_COLUMNS = [
        'name' => 'Name',
        'email' => 'Email',
        'email_verified' => 'Email verified',
        'created_at' => 'Joined',
    ];

    public function create(): Response
    {
        $actor = auth()->user();

        return Inertia::render('Users/Create', [
            'available_roles' => $actor->can('roles.manage')
                ? Role::query()
                    ->where('guard_name', 'web')
                    ->when(! $actor->can('permissions.manage'), fn ($query) => $query->where('name', '!=', 'administrator'))
                    ->orderBy('name')
                    ->get(['id', 'name'])
                : [],
        ]);
    }

    public function store(StoreUserRequest $request, AuditLogger $auditLogger)
    {
        $data = $request->validated();
        $roleIds = $data['role_ids'] ?? [];
        unset($data['role_ids']);

        $roles = Role::query()->where('guard_name', 'web')->whereIn('id', $roleIds)->get();

        abort_if($roles->contains('name', 'administrator') && ! $request->user()->can('permissions.manage'), 403);

        $attributes = $data;
        unset($attributes['password_confirmation']);
        $user = User::create($attributes);
        $auditLogger->log('user.created', $user, ['fields' => array_keys($attributes)]);

        if ($roles->isNotEmpty()) {
            $user->syncRoles($roles);
            $auditLogger->log('user.roles_updated', $user, [
                'roles_added' => $roles->modelKeys(),
                'roles_removed' => [],
            ]);
        }

        $user->sendEmailVerificationNotification();

        return to_route('users.index')->with('status', 'User account created. A verification email has been sent.');
    }

    public function show(User $user): Response
    {
        return Inertia::render('Users/Show', [
            'person' => $this->formatUserDetails($user),
            'available_roles' => auth()->user()->can('roles.manage')
                ? Role::query()->where('guard_name', 'web')->orderBy('name')->get(['id', 'name'])
                : [],
        ]);
    }

    public function edit(User $user): Response
    {
        return Inertia::render('Users/Edit', [
            'person' => $this->formatUserDetails($user),
            'available_roles' => auth()->user()->can('roles.manage')
                ? Role::query()->where('guard_name', 'web')->orderBy('name')->get(['id', 'name'])
                : [],
        ]);
    }

    public function update(UpdateUserRequest $request, User $user, AuditLogger $auditLogger)
    {
        $data = $request->validated();
        $emailChanged = mb_strtolower($user->email) !== $data['email'];

        $user->fill($data);

        if ($emailChanged) {
            $user->forceFill(['email_verified_at' => null]);
        }

        $user->save();

        $auditLogger->log('user.updated', $user, ['fields' => array_keys($user->getChanges())]);

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        $status = $emailChanged
            ? 'User updated. A verification email has been sent to the new address.'
            : 'User updated.';

        return to_route('users.show', $user)->with('status', $status);
    }

    public function updateStatus(UpdateUserStatusRequest $request, User $user, AuditLogger $auditLogger)
    {
        $isActive = $request->boolean('is_active');

        if (! $isActive) {
            abort_unless($request->user()->can('users.deactivate', $user), 403);
        }

        if ($user->is_active !== $isActive) {
            $user->forceFill(['is_active' => $isActive])->save();
            $auditLogger->log($isActive ? 'user.activated' : 'user.deactivated', $user, ['fields' => ['is_active']]);
        }

        $status = $isActive ? 'User account activated.' : 'User account deactivated.';

        return to_route('users.show', $user)->with('status', $status);
    }

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', Rule::in(['10', '25', '50', '100', 'all', 10, 25, 50, 100])],
            'sort' => ['nullable', Rule::in(['name', 'email', 'created_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $search = trim($filters['search'] ?? '');
        $allMode = ($filters['per_page'] ?? 25) === 'all';
        $perPage = $allMode ? self::BATCH_SIZE : (int) ($filters['per_page'] ?? 25);
        $sort = $filters['sort'] ?? 'name';
        $direction = $filters['direction'] ?? 'asc';

        $query = $this->usersQuery($search, $sort, $direction);

        if ($allMode) {
            $total = (clone $query)->count();
            $users = $query->cursorPaginate(self::BATCH_SIZE);
            $userData = [
                'data' => array_map($this->formatUser(...), $users->items()),
                'total' => $total,
                'has_more' => $users->hasMorePages(),
                'next_cursor' => $users->nextCursor()?->encode(),
            ];
        } else {
            $users = $query->paginate($perPage)->withQueryString()->through($this->formatUser(...));
            $userData = $users;
        }

        return Inertia::render('Users/Index', [
            'users' => $userData,
            'filters' => [
                'search' => $search,
                'per_page' => $allMode ? 'all' : $perPage,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->validateExportFilters($request);
        $columns = $filters['columns'];
        $users = $this->exportQuery($filters);

        return response()->streamDownload(function () use ($users, $columns): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, array_map(fn (string $column): string => self::EXPORT_COLUMNS[$column], $columns), ',', '"', '', "\r\n");

            foreach ($users->cursor() as $user) {
                fputcsv($output, array_map($this->safeCsvCell(...), $this->exportValues($user, $columns)), ',', '"', '', "\r\n");
            }

            fclose($output);
        }, 'users-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportXlsx(Request $request)
    {
        $filters = $this->validateExportFilters($request);
        $columns = $filters['columns'];
        $users = $this->exportQuery($filters);
        $path = tempnam(sys_get_temp_dir(), 'users-xlsx-');

        abort_if($path === false, 500, 'Unable to prepare the export.');

        $writer = new XlsxWriter;

        try {
            $writer->openToFile($path);
            $writer->addRow(new Row(array_map(
                fn (string $column): StringCell => new StringCell(self::EXPORT_COLUMNS[$column]),
                $columns,
            )));

            foreach ($users->cursor() as $user) {
                $writer->addRow(new Row(array_map(
                    static fn (string $value): StringCell => new StringCell($value),
                    $this->exportValues($user, $columns),
                )));
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

        return response()->download($path, 'users-'.now()->format('Ymd-His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function exportPdf(Request $request)
    {
        $filters = $this->validateExportFilters($request);
        $columns = $filters['columns'];
        $users = $this->exportQuery($filters);

        abort_if((clone $users)->count() > self::PDF_ROW_LIMIT, 422, 'PDF exports are limited to 1,000 users. Narrow your search and try again.');

        $rows = $users->get()->map(fn (User $user): array => $this->exportValues($user, $columns))->all();

        return Pdf::loadView('exports.users', [
            'columns' => array_map(fn (string $column): string => self::EXPORT_COLUMNS[$column], $columns),
            'rows' => $rows,
            'generatedAt' => now()->format('Y-m-d H:i:s'),
        ])->setPaper('a4', 'landscape')->download('users-'.now()->format('Ymd-His').'.pdf');
    }

    public function batch(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(['name', 'email', 'created_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'cursor' => ['nullable', 'string', 'max:2048'],
        ]);

        $users = $this->usersQuery(
            trim($filters['search'] ?? ''),
            $filters['sort'] ?? 'name',
            $filters['direction'] ?? 'asc',
        )->cursorPaginate(self::BATCH_SIZE, ['*'], 'cursor', $filters['cursor'] ?? null);

        return response()->json([
            'data' => array_map($this->formatUser(...), $users->items()),
            'has_more' => $users->hasMorePages(),
            'next_cursor' => $users->nextCursor()?->encode(),
        ]);
    }

    private function usersQuery(string $search, string $sort, string $direction): Builder
    {
        $query = User::query()
            ->select(['id', 'prefix', 'first_name', 'middle_name', 'last_name', 'suffix', 'email', 'email_verified_at', 'created_at'])
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    foreach (['prefix', 'first_name', 'middle_name', 'last_name', 'suffix', 'email'] as $field) {
                        $query->orWhere($field, 'like', '%'.$search.'%');
                    }
                });
            });

        if ($sort === 'name') {
            $query->orderBy('last_name', $direction)->orderBy('first_name', $direction);
        } else {
            $query->orderBy($sort, $direction);
        }

        return $query->orderBy('id');
    }

    private function safeCsvCell(string $value): string
    {
        return preg_match('/^[\x00-\x20]*[=+@\-]/u', $value) === 1 ? "'".$value : $value;
    }

    /** @return array{search?: string, per_page?: string|int, sort?: string, direction?: string, columns: list<string>} */
    private function validateExportFilters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', Rule::in(['10', '25', '50', '100', 'all', 10, 25, 50, 100])],
            'sort' => ['nullable', Rule::in(['name', 'email', 'created_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'columns' => ['required', 'array', 'min:1', 'max:4'],
            'columns.*' => ['required', 'string', 'distinct:strict', Rule::in(array_keys(self::EXPORT_COLUMNS))],
        ]);
    }

    /** @param array{search?: string, sort?: string, direction?: string, columns: list<string>} $filters */
    private function exportQuery(array $filters): Builder
    {
        return $this->usersQuery(
            trim($filters['search'] ?? ''),
            $filters['sort'] ?? 'name',
            $filters['direction'] ?? 'asc',
        );
    }

    /** @param list<string> $columns
     * @return list<string>
     */
    private function exportValues(User $user, array $columns): array
    {
        return array_map(fn (string $column): string => match ($column) {
            'name' => $user->full_name,
            'email' => $user->email,
            'email_verified' => $user->hasVerifiedEmail() ? 'Yes' : 'No',
            'created_at' => $user->created_at?->toDateTimeString() ?? '',
        }, $columns);
    }

    /** @return array{id: int, full_name: string, email: string, email_verified: bool, created_at: ?string} */
    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'email_verified' => $user->hasVerifiedEmail(),
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }

    /** @return array{id: int, prefix: ?string, first_name: string, middle_name: ?string, last_name: string, suffix: ?string, full_name: string, email: string, email_verified: bool, is_active: bool, role_ids: list<int>, role_names: list<string>, can_manage_status: bool, created_at: ?string} */
    private function formatUserDetails(User $user): array
    {
        return [
            'id' => $user->id,
            'prefix' => $user->prefix,
            'first_name' => $user->first_name,
            'middle_name' => $user->middle_name,
            'last_name' => $user->last_name,
            'suffix' => $user->suffix,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'email_verified' => $user->hasVerifiedEmail(),
            'is_active' => $user->is_active,
            'role_ids' => $user->roles()->where('guard_name', 'web')->pluck('roles.id')->all(),
            'role_names' => $user->getRoleNames()->all(),
            'can_manage_status' => auth()->user()?->can('users.update')
                && auth()->user()?->can('users.deactivate', $user),
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }
}
