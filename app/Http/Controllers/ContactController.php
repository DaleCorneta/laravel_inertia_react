<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactRequest;
use App\Http\Requests\UpdateContactRequest;
use App\Models\Contact;
use App\Models\ContactAttachment;
use App\Services\AuditLogger;
use App\Services\ContactAttachmentService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ContactController extends Controller
{
    private const BATCH_SIZE = 25;

    private const PDF_ROW_LIMIT = 1000;

    private const EXPORT_COLUMNS = [
        'name' => 'Name',
        'organization' => 'Organization',
        'title' => 'Title',
        'email' => 'Email',
        'phone' => 'Phone',
        'archived_at' => 'Archived',
    ];

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'view' => ['nullable', Rule::in(['active', 'trash'])],
            'per_page' => ['nullable', Rule::in(['10', '25', '50', '100', 'all', 10, 25, 50, 100])],
            'sort' => ['nullable', Rule::in(['name', 'organization', 'email', 'created_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);
        $search = trim($filters['search'] ?? '');
        $view = $filters['view'] ?? 'active';
        abort_if($view === 'trash' && ! $request->user()->can('contacts.view_trash'), 403);
        $allMode = ($filters['per_page'] ?? 25) === 'all';
        $perPage = $allMode ? self::BATCH_SIZE : (int) ($filters['per_page'] ?? 25);
        $sort = $filters['sort'] ?? 'name';
        $direction = $filters['direction'] ?? 'asc';
        $query = $this->contactsQuery($search, $view, $sort, $direction);

        if ($allMode) {
            $contactsPage = $query->cursorPaginate(self::BATCH_SIZE);
            $contacts = [
                'data' => array_map($this->formatContact(...), $contactsPage->items()),
                'total' => (clone $query)->count(),
                'has_more' => $contactsPage->hasMorePages(),
                'next_cursor' => $contactsPage->nextCursor()?->encode(),
            ];
        } else {
            $contacts = $query->paginate($perPage)->withQueryString()->through($this->formatContact(...));
        }

        return Inertia::render('Contacts/Index', [
            'contacts' => $contacts,
            'filters' => ['search' => $search, 'view' => $view, 'per_page' => $allMode ? 'all' : $perPage, 'sort' => $sort, 'direction' => $direction],
            'export_columns' => self::EXPORT_COLUMNS,
            'can_create_contacts' => $request->user()->can('contacts.create'),
            'can_edit_contacts' => $request->user()->can('contacts.update'),
            'can_archive_contacts' => $request->user()->can('contacts.archive'),
            'can_delete_contacts' => $request->user()->can('contacts.delete'),
            'can_view_trash' => $request->user()->can('contacts.view_trash'),
            'can_restore_contacts' => $request->user()->can('contacts.restore'),
            'can_force_delete_contacts' => $request->user()->can('contacts.force-delete'),
        ]);
    }

    public function batch(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'view' => ['nullable', Rule::in(['active', 'trash'])],
            'sort' => ['nullable', Rule::in(['name', 'organization', 'email', 'created_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'cursor' => ['nullable', 'string', 'max:2048'],
        ]);
        $view = $filters['view'] ?? 'active';
        abort_if($view === 'trash' && ! $request->user()->can('contacts.view_trash'), 403);
        $contacts = $this->contactsQuery(trim($filters['search'] ?? ''), $view, $filters['sort'] ?? 'name', $filters['direction'] ?? 'asc')
            ->cursorPaginate(self::BATCH_SIZE, ['*'], 'cursor', $filters['cursor'] ?? null);

        return response()->json([
            'data' => array_map($this->formatContact(...), $contacts->items()),
            'has_more' => $contacts->hasMorePages(),
            'next_cursor' => $contacts->nextCursor()?->encode(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->validateExportFilters($request);
        $columns = $filters['columns'];
        $contacts = $this->exportQuery($filters, $request);

        return response()->streamDownload(function () use ($contacts, $columns): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, array_map(fn (string $column): string => self::EXPORT_COLUMNS[$column], $columns), ',', '"', '', "\r\n");
            foreach ($contacts->cursor() as $contact) {
                fputcsv($output, array_map($this->safeCsvCell(...), $this->exportValues($contact, $columns)), ',', '"', '', "\r\n");
            }
            fclose($output);
        }, 'contacts-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function exportXlsx(Request $request)
    {
        $filters = $this->validateExportFilters($request);
        $columns = $filters['columns'];
        $contacts = $this->exportQuery($filters, $request);
        $path = tempnam(sys_get_temp_dir(), 'contacts-xlsx-');
        abort_if($path === false, 500, 'Unable to prepare the export.');
        $writer = new XlsxWriter;
        try {
            $writer->openToFile($path);
            $writer->addRow(new Row(array_map(fn (string $column): StringCell => new StringCell(self::EXPORT_COLUMNS[$column]), $columns)));
            foreach ($contacts->cursor() as $contact) {
                $writer->addRow(new Row(array_map(static fn (string $value): StringCell => new StringCell($value), $this->exportValues($contact, $columns))));
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

        return response()->download($path, 'contacts-'.now()->format('Ymd-His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function exportPdf(Request $request)
    {
        $filters = $this->validateExportFilters($request);
        $columns = $filters['columns'];
        $contacts = $this->exportQuery($filters, $request);
        abort_if((clone $contacts)->count() > self::PDF_ROW_LIMIT, 422, 'PDF exports are limited to 1,000 contacts. Narrow your search and try again.');
        $rows = $contacts->get()->map(fn (Contact $contact): array => $this->exportValues($contact, $columns))->all();

        return Pdf::loadView('exports.contacts', [
            'columns' => array_map(fn (string $column): string => self::EXPORT_COLUMNS[$column], $columns),
            'rows' => $rows,
            'generatedAt' => now()->format('Y-m-d H:i:s'),
        ])->setPaper('a4', 'landscape')->download('contacts-'.now()->format('Ymd-His').'.pdf');
    }

    public function create(): Response
    {
        return Inertia::render('Contacts/Create');
    }

    public function edit(Contact $contact): Response
    {
        return Inertia::render('Contacts/Edit', [
            'contact' => $contact->only([
                'id', 'prefix', 'first_name', 'middle_name', 'last_name', 'suffix',
                'organization', 'title', 'email', 'phone', 'notes',
            ]),
            'attachments' => $contact->attachments()->orderBy('id')->get(['id', 'original_name', 'size']),
        ]);
    }

    public function store(StoreContactRequest $request, AuditLogger $auditLogger, ContactAttachmentService $attachmentService): RedirectResponse
    {
        $data = $request->validated();
        $files = $data['attachments'] ?? [];
        unset($data['attachments']);
        $attachmentService->inspect($files);

        DB::transaction(function () use ($data, $files, $request, $auditLogger, $attachmentService): void {
            $contact = Contact::create([...$data, 'owner_id' => $request->user()->id]);
            $auditLogger->log('contact.created', $contact, ['fields' => array_keys($data)]);
            $attachmentService->store($contact, $request->user(), $files, $auditLogger);
        });

        return to_route('contacts.index')->with('status', 'Contact created.');
    }

    public function update(UpdateContactRequest $request, Contact $contact, AuditLogger $auditLogger, ContactAttachmentService $attachmentService): RedirectResponse
    {
        $data = $request->validated();
        $files = $data['attachments'] ?? [];
        unset($data['attachments']);
        $attachmentService->inspect($files);

        DB::transaction(function () use ($contact, $data, $files, $request, $auditLogger, $attachmentService): void {
            $contact->fill($data);
            $changedFields = array_keys($contact->getDirty());
            $contact->save();

            if ($changedFields !== []) {
                $auditLogger->log('contact.updated', $contact, ['fields' => $changedFields]);
            }
            $attachmentService->store($contact, $request->user(), $files, $auditLogger);
        });

        return to_route('contacts.index')->with('status', 'Contact updated.');
    }

    public function downloadAttachment(Contact $contact, ContactAttachment $attachment)
    {
        $attachment = $contact->attachments()->whereKey($attachment->getKey())->firstOrFail();

        return Storage::disk('local')->download($attachment->path, $attachment->original_name, [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function deleteAttachment(Contact $contact, ContactAttachment $attachment, ContactAttachmentService $attachmentService, AuditLogger $auditLogger): RedirectResponse
    {
        $attachment = $contact->attachments()->whereKey($attachment->getKey())->firstOrFail();
        DB::transaction(fn () => $attachmentService->delete($attachment, $auditLogger));

        return back()->with('status', 'Attachment deleted.');
    }

    public function archive(Contact $contact, AuditLogger $auditLogger): RedirectResponse
    {
        abort_if($contact->archived_at !== null, 404);
        DB::transaction(function () use ($contact, $auditLogger): void {
            $contact->forceFill(['archived_at' => now()])->save();
            $auditLogger->log('contact.archived', $contact);
        });

        return back()->with('status', 'Contact archived.');
    }

    public function unarchive(Contact $contact, AuditLogger $auditLogger): RedirectResponse
    {
        abort_if($contact->archived_at === null, 404);
        DB::transaction(function () use ($contact, $auditLogger): void {
            $contact->forceFill(['archived_at' => null])->save();
            $auditLogger->log('contact.unarchived', $contact);
        });

        return back()->with('status', 'Contact restored from archive.');
    }

    public function destroy(Contact $contact, AuditLogger $auditLogger): RedirectResponse
    {
        DB::transaction(function () use ($contact, $auditLogger): void {
            $contact->delete();
            $auditLogger->log('contact.trashed', $contact);
        });

        return back()->with('status', 'Contact moved to trash.');
    }

    public function restore(Contact $contact, AuditLogger $auditLogger): RedirectResponse
    {
        abort_unless($contact->trashed(), 404);
        DB::transaction(function () use ($contact, $auditLogger): void {
            $contact->restore();
            $auditLogger->log('contact.restored', $contact);
        });

        return back()->with('status', 'Contact restored.');
    }

    public function forceDestroy(Contact $contact, AuditLogger $auditLogger, ContactAttachmentService $attachmentService): RedirectResponse
    {
        abort_unless($contact->trashed(), 404);
        DB::transaction(function () use ($contact, $auditLogger, $attachmentService): void {
            $auditLogger->log('contact.force_deleted', $contact);
            $attachmentService->purge($contact);
            $contact->forceDelete();
        });

        return back()->with('status', 'Contact permanently deleted.');
    }

    private function contactsQuery(string $search, string $view, string $sort, string $direction): Builder
    {
        $query = Contact::query()->search($search);
        if ($view === 'trash') {
            $query->onlyTrashed();
        }
        if ($sort === 'name') {
            $query->orderBy('last_name', $direction)->orderBy('first_name', $direction);
        } elseif (in_array($sort, ['organization', 'email'], true)) {
            $query->select('contacts.*')->selectRaw("COALESCE($sort, '') as {$sort}_sort")->orderBy("{$sort}_sort", $direction);
        } else {
            $query->orderBy($sort, $direction);
        }

        return $query->orderBy('id');
    }

    /** @return array{id: int, full_name: string, organization: ?string, title: ?string, email: ?string, phone: ?string, archived_at: ?string} */
    private function formatContact(Contact $contact): array
    {
        return [
            'id' => $contact->id,
            'full_name' => $contact->full_name,
            'organization' => $contact->organization,
            'title' => $contact->title,
            'email' => $contact->email,
            'phone' => $contact->phone,
            'archived_at' => $contact->archived_at?->toIso8601String(),
        ];
    }

    /** @return array{search?: string, view?: string, sort?: string, direction?: string, columns: list<string>} */
    private function validateExportFilters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'view' => ['nullable', Rule::in(['active', 'trash'])],
            'sort' => ['nullable', Rule::in(['name', 'organization', 'email', 'created_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'columns' => ['required', 'array', 'min:1', 'max:6'],
            'columns.*' => ['required', 'string', 'distinct:strict', Rule::in(array_keys(self::EXPORT_COLUMNS))],
        ]);
    }

    /** @param array{search?: string, view?: string, sort?: string, direction?: string, columns: list<string>} $filters */
    private function exportQuery(array $filters, Request $request): Builder
    {
        $view = $filters['view'] ?? 'active';
        abort_if($view === 'trash' && ! $request->user()->can('contacts.view_trash'), 403);

        return $this->contactsQuery(
            trim($filters['search'] ?? ''),
            $view,
            $filters['sort'] ?? 'name',
            $filters['direction'] ?? 'asc',
        );
    }

    /** @param list<string> $columns @return list<string> */
    private function exportValues(Contact $contact, array $columns): array
    {
        return array_map(fn (string $column): string => match ($column) {
            'name' => $contact->full_name,
            'organization' => $contact->organization ?? '',
            'title' => $contact->title ?? '',
            'email' => $contact->email ?? '',
            'phone' => $contact->phone ?? '',
            'archived_at' => $contact->archived_at?->toDateTimeString() ?? '',
        }, $columns);
    }

    private function safeCsvCell(string $value): string
    {
        return preg_match('/^[\x00-\x20]*[=+@\-]/u', $value) === 1 ? "'".$value : $value;
    }
}
