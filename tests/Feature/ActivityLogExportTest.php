<?php

namespace Tests\Feature;

use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ActivityLogExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_exports_require_authentication_and_audit_permission(): void
    {
        foreach (['activity-logs.export', 'activity-logs.export.xlsx', 'activity-logs.export.pdf'] as $route) {
            $this->get(route($route, ['columns' => ['action']]))->assertRedirect(route('login'));
        }

        $this->actingAs(User::factory()->create());
        foreach (['activity-logs.export', 'activity-logs.export.xlsx', 'activity-logs.export.pdf'] as $route) {
            $this->get(route($route, ['columns' => ['action']]))->assertForbidden();
        }
    }

    public function test_csv_reuses_search_and_event_filters_and_only_exports_allowlisted_columns(): void
    {
        $viewer = User::factory()->create(['first_name' => '=HYPERLINK("https://example.test","Click")', 'middle_name' => null, 'last_name' => 'User']);
        $viewer->givePermissionTo(Permission::findOrCreate('activity-logs.view', 'web'));
        $subject = User::factory()->create(['first_name' => 'Needle']);
        $this->activity($viewer, $subject, ['fields' => ['email'], 'password' => 'SecretPassword123!']);
        $other = User::factory()->create(['first_name' => 'Unrelated']);
        $this->activity($viewer, $other);

        $response = $this->actingAs($viewer)->get(route('activity-logs.export', [
            'search' => 'Needle', 'event' => 'user.created', 'columns' => ['actor', 'subject', 'changes'],
        ]));

        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = substr($response->streamedContent(), 3);
        $rows = array_map(static fn (string $line): array => str_getcsv($line), explode("\r\n", trim($csv)));
        $this->assertSame(['Performed by', 'Subject', 'Change details'], $rows[0]);
        $this->assertCount(2, $rows);
        $this->assertSame("'=HYPERLINK(\"https://example.test\",\"Click\") User", $rows[1][0]);
        $this->assertSame(['Fields: Email'], [$rows[1][2]]);
        $this->assertStringNotContainsString('SecretPassword', $csv);

        $this->actingAs($viewer)->get(route('activity-logs.export', ['columns' => ['properties']]))
            ->assertSessionHasErrors('columns.0');
    }

    public function test_xlsx_writes_audit_labels_as_strings_without_formula_cells(): void
    {
        $viewer = User::factory()->create(['first_name' => '=HYPERLINK("https://example.test","Click")', 'middle_name' => null, 'last_name' => 'User']);
        $viewer->givePermissionTo(Permission::findOrCreate('activity-logs.view', 'web'));
        $subject = User::factory()->create(['first_name' => 'Formula subject']);
        $this->activity($viewer, $subject);

        $response = $this->actingAs($viewer)->get(route('activity-logs.export.xlsx', [
            'search' => 'Formula subject', 'columns' => ['actor', 'action'],
        ]));
        $path = tempnam(sys_get_temp_dir(), 'activity-xlsx-test-');
        file_put_contents($path, $response->streamedContent());
        $reader = new XlsxReader;
        $zip = new \ZipArchive;
        try {
            $this->assertTrue($zip->open($path));
            $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
            $zip->close();
            $reader->open($path);
            $sheets = $reader->getSheetIterator();
            $sheets->rewind();
            $rows = array_values(iterator_to_array($sheets->current()->getRowIterator()));
        } finally {
            $reader->close();
            unlink($path);
        }

        $response->assertOk();
        $this->assertSame(['Performed by', 'Action'], $rows[0]->toArray());
        $this->assertSame('=HYPERLINK("https://example.test","Click") User', $rows[1]->cells[0]->getValue());
        $this->assertIsString($sheetXml);
        $this->assertDoesNotMatchRegularExpression('/<f(?:\s|>)/', $sheetXml);
    }

    public function test_pdf_uses_selected_filtered_safe_rows_and_escapes_report_content(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(Permission::findOrCreate('activity-logs.view', 'web'));
        $subject = User::factory()->create(['first_name' => '<script>Needle</script>', 'middle_name' => null, 'last_name' => 'Subject']);
        $this->activity($viewer, $subject, ['password' => 'never export']);
        $this->activity($viewer, User::factory()->create(['first_name' => 'Other']));

        $document = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $document->shouldReceive('setPaper')->once()->with('a4', 'landscape')->andReturnSelf();
        $document->shouldReceive('download')->once()->with(\Mockery::type('string'))
            ->andReturn(response('pdf response', 200, ['Content-Type' => 'application/pdf']));
        Pdf::shouldReceive('loadView')->once()->with('exports.activity-logs', \Mockery::on(function (array $data): bool {
            $this->assertSame(['Subject'], $data['columns']);
            $this->assertSame([['<script>Needle</script> Subject']], $data['rows']);
            $this->assertIsString($data['generatedAt']);

            return true;
        }))->andReturn($document);

        $this->actingAs($viewer)->get(route('activity-logs.export.pdf', [
            'search' => 'Needle', 'event' => 'user.created', 'columns' => ['subject'],
        ]))->assertOk()->assertHeader('content-type', 'application/pdf');

        $html = view('exports.activity-logs', [
            'columns' => ['Subject'], 'rows' => [['<script>alert(1)</script>']], 'generatedAt' => '2026-10-16 12:00:00',
        ])->render();
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert', $html);
    }

    public function test_pdf_export_rejects_results_above_its_safe_row_limit(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(Permission::findOrCreate('activity-logs.view', 'web'));
        for ($index = 0; $index < 1001; $index++) {
            Activity::query()->create(['log_name' => 'audit', 'description' => 'user.created', 'event' => 'user.created', 'properties' => []]);
        }

        $this->actingAs($viewer)->get(route('activity-logs.export.pdf', ['columns' => ['action']]))->assertStatus(422);
    }

    private function activity(User $actor, User $subject, array $properties = []): Activity
    {
        return Activity::query()->create([
            'log_name' => 'audit', 'description' => 'user.created', 'event' => 'user.created',
            'causer_type' => User::class, 'causer_id' => $actor->id,
            'subject_type' => User::class, 'subject_id' => $subject->id,
            'properties' => $properties,
        ]);
    }
}
