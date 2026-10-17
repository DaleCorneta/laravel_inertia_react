<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ActivityLogController extends Controller
{
    /** @var list<string> */
    private const FIELDS = [
        'prefix', 'first_name', 'middle_name', 'last_name', 'suffix', 'email', 'is_active', 'name',
    ];

    private const EXPORT_COLUMNS = [
        'action' => 'Action', 'actor' => 'Performed by', 'subject' => 'Subject',
        'changes' => 'Change details', 'occurred_at' => 'Date and time',
    ];

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'event' => ['nullable', Rule::in(AuditLogger::eventNames())],
            'per_page' => ['nullable', Rule::in(['10', '25', '50', '100', 10, 25, 50, 100])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $search = trim($filters['search'] ?? '');
        $perPage = (int) ($filters['per_page'] ?? 25);

        $activities = $this->activityQuery($filters)
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Activity $activity): array => $this->formatActivity($activity));

        return Inertia::render('ActivityLogs/Index', [
            'activities' => $activities,
            'events' => AuditLogger::eventNames(),
            'filters' => [
                'search' => $search,
                'event' => $filters['event'] ?? '',
                'per_page' => $perPage,
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->validateExportFilters($request);
        $columns = $filters['columns'];
        $activities = $this->activityQuery($filters);

        return response()->streamDownload(function () use ($activities, $columns): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, array_map(fn (string $column): string => self::EXPORT_COLUMNS[$column], $columns), ',', '"', '', "\r\n");

            $activities->chunk(500, function ($items) use ($output, $columns): void {
                foreach ($items as $activity) {
                    fputcsv($output, array_map($this->safeCsvCell(...), $this->exportValues($activity, $columns)), ',', '"', '', "\r\n");
                }
            });

            fclose($output);
        }, 'activity-logs-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function exportXlsx(Request $request)
    {
        $filters = $this->validateExportFilters($request);
        $columns = $filters['columns'];
        $activities = $this->activityQuery($filters);
        $path = tempnam(sys_get_temp_dir(), 'activity-xlsx-');

        abort_if($path === false, 500, 'Unable to prepare the export.');

        $writer = new XlsxWriter;

        try {
            $writer->openToFile($path);
            $writer->addRow(new Row(array_map(fn (string $column): StringCell => new StringCell(self::EXPORT_COLUMNS[$column]), $columns)));
            $activities->chunk(500, function ($items) use ($writer, $columns): void {
                foreach ($items as $activity) {
                    $writer->addRow(new Row(array_map(static fn (string $value): StringCell => new StringCell($value), $this->exportValues($activity, $columns))));
                }
            });
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

        return response()->download($path, 'activity-logs-'.now()->format('Ymd-His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function exportPdf(Request $request)
    {
        $filters = $this->validateExportFilters($request);
        $columns = $filters['columns'];
        $activities = $this->activityQuery($filters);
        abort_if((clone $activities)->count() > 1000, 422, 'PDF exports are limited to 1,000 activity records. Narrow your search and try again.');
        $rows = $activities->get()->map(fn (Activity $activity): array => $this->exportValues($activity, $columns))->all();

        return Pdf::loadView('exports.activity-logs', [
            'columns' => array_map(fn (string $column): string => self::EXPORT_COLUMNS[$column], $columns),
            'rows' => $rows,
            'generatedAt' => now()->format('Y-m-d H:i:s'),
        ])->setPaper('a4', 'landscape')->download('activity-logs-'.now()->format('Ymd-His').'.pdf');
    }

    private function activityQuery(array $filters): Builder
    {
        $search = trim($filters['search'] ?? '');

        return Activity::query()->inLog('audit')->with(['causer', 'subject'])
            ->when(isset($filters['event']), fn (Builder $query) => $query->where('event', $filters['event']))
            ->when($search !== '', function (Builder $query) use ($search) {
                $term = '%'.$search.'%';
                $query->where(function (Builder $query) use ($term) {
                    $query->where('event', 'like', $term)->orWhere('description', 'like', $term)
                        ->orWhereHasMorph('causer', [User::class], fn (Builder $userQuery) => $this->whereUserNameMatches($userQuery, $term))
                        ->orWhereHasMorph('subject', [User::class, Role::class], function (Builder $subjectQuery, string $type) use ($term) {
                            $type === User::class ? $this->whereUserNameMatches($subjectQuery, $term) : $subjectQuery->where('name', 'like', $term);
                        });
                });
            })->orderByDesc('created_at')->orderByDesc('id');
    }

    private function validateExportFilters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'event' => ['nullable', Rule::in(AuditLogger::eventNames())],
            'columns' => ['required', 'array', 'list', 'min:1', 'max:5'],
            'columns.*' => ['required', 'string', 'distinct:strict', Rule::in(array_keys(self::EXPORT_COLUMNS))],
        ]);
    }

    private function safeCsvCell(string $value): string
    {
        return preg_match('/^[\x00-\x20]*[=+@\-]/u', $value) === 1 ? "'".$value : $value;
    }

    private function exportValues(Activity $activity, array $columns): array
    {
        $row = $this->formatActivity($activity);

        return array_map(static fn (string $column): string => match ($column) {
            'changes' => implode('; ', $row['changes']),
            default => $row[$column] ?? '',
        }, $columns);
    }

    private function whereUserNameMatches(Builder $query, string $term): void
    {
        $query->where(function (Builder $query) use ($term) {
            foreach (['prefix', 'first_name', 'middle_name', 'last_name', 'suffix'] as $field) {
                $query->orWhere($field, 'like', $term);
            }
        });
    }

    /** @return array{id: int, action: string, actor: string, subject: string, changes: list<string>, occurred_at: ?string} */
    private function formatActivity(Activity $activity): array
    {
        $properties = $activity->properties?->toArray() ?? [];
        $changes = [];

        $fieldValues = is_array($properties['fields'] ?? null) ? $properties['fields'] : [];
        $fields = array_values(array_intersect(self::FIELDS, array_filter($fieldValues, 'is_string')));

        if ($fields !== []) {
            $changes[] = 'Fields: '.implode(', ', array_map(Str::headline(...), $fields));
        }

        foreach (['roles_added' => 'Roles added', 'roles_removed' => 'Roles removed', 'permissions_added' => 'Permissions added', 'permissions_removed' => 'Permissions removed'] as $key => $label) {
            $ids = array_slice(array_values(array_unique(array_filter(
                is_array($properties[$key] ?? null) ? $properties[$key] : [],
                static fn (mixed $id): bool => is_int($id) && $id > 0,
            ))), 0, 100);

            if ($ids !== []) {
                $changes[] = $label.': '.implode(', ', array_map(static fn (int $id): string => '#'.$id, $ids));
            }
        }

        return [
            'id' => $activity->id,
            'action' => Str::headline(str_replace('.', ' ', $activity->event ?? 'activity')),
            'actor' => $this->modelLabel($activity->causer),
            'subject' => $this->modelLabel($activity->subject),
            'changes' => $changes,
            'occurred_at' => $activity->created_at?->toIso8601String(),
        ];
    }

    private function modelLabel(?Model $model): string
    {
        return match (true) {
            $model instanceof User => $model->display_name,
            $model instanceof Role => 'Role: '.$model->name,
            $model === null => 'System',
            default => class_basename($model).' #'.$model->getKey(),
        };
    }
}
