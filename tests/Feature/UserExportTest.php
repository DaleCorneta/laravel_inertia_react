<?php

namespace Tests\Feature;

use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Tests\TestCase;

class UserExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_csv_export_requires_authentication_and_users_view_permission(): void
    {
        $this->get(route('users.export', ['columns' => ['name']]))
            ->assertRedirect(route('login'));
        $this->get(route('users.export.xlsx', ['columns' => ['name']]))
            ->assertRedirect(route('login'));
        $this->get(route('users.export.pdf', ['columns' => ['name']]))
            ->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())
            ->get(route('users.export', ['columns' => ['name']]))
            ->assertForbidden();
        $this->get(route('users.export.xlsx', ['columns' => ['name']]))
            ->assertForbidden();
        $this->get(route('users.export.pdf', ['columns' => ['name']]))
            ->assertForbidden();
    }

    public function test_export_preserves_search_and_sort_and_exports_selected_columns_across_all_matches(): void
    {
        $admin = User::factory()->create();
        User::factory()->count(12)->create([
            'first_name' => 'Needle',
            'middle_name' => null,
            'last_name' => 'Member',
        ]);
        User::factory()->create(['first_name' => 'Other']);
        $this->makeAdministrator($admin);

        $response = $this->actingAs($admin)->get(route('users.export', [
            'search' => 'Needle',
            'per_page' => 10,
            'sort' => 'email',
            'direction' => 'desc',
            'columns' => ['email', 'name'],
        ]));

        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('content-disposition');

        $csv = substr($response->streamedContent(), 3);
        $rows = array_map(static fn (string $line): array => str_getcsv($line), explode("\r\n", trim($csv)));

        $this->assertSame(['Email', 'Name'], $rows[0]);
        $this->assertCount(13, $rows);
        $this->assertSame(
            collect($rows)->skip(1)->pluck(0)->sortDesc()->values()->all(),
            collect($rows)->skip(1)->pluck(0)->values()->all(),
        );
        $this->assertStringContainsString('Needle Member', implode(',', $rows[1]));
    }

    public function test_export_neutralizes_formula_cells_and_rejects_unapproved_columns(): void
    {
        $admin = User::factory()->create();
        User::factory()->create([
            'first_name' => '=HYPERLINK("https://example.test","Click")',
            'middle_name' => null,
            'last_name' => 'Member',
            'email' => '=formula@example.test',
        ]);
        $this->makeAdministrator($admin);

        $response = $this->actingAs($admin)->get(route('users.export', [
            'search' => 'formula',
            'columns' => ['name', 'email'],
        ]));
        $csv = substr($response->streamedContent(), 3);
        $rows = array_map(static fn (string $line): array => str_getcsv($line), explode("\r\n", trim($csv)));

        $this->assertSame(['Name', 'Email'], $rows[0]);
        $this->assertSame("'=HYPERLINK(\"https://example.test\",\"Click\") Member", $rows[1][0]);
        $this->assertSame("'=formula@example.test", $rows[1][1]);

        $this->actingAs($admin)
            ->get(route('users.export', ['columns' => ['password']]))
            ->assertSessionHasErrors('columns.0');
    }

    public function test_xlsx_export_preserves_filters_columns_and_formula_values_as_strings(): void
    {
        $admin = User::factory()->create();
        for ($index = 1; $index <= 11; $index++) {
            User::factory()->create([
                'first_name' => 'Formula',
                'middle_name' => null,
                'last_name' => 'Member',
                'email' => 'formula-member-'.$index.'@example.test',
            ]);
        }
        User::factory()->create([
            'first_name' => '=HYPERLINK("https://example.test","Click")',
            'middle_name' => null,
            'last_name' => 'Member',
            'email' => 'formula-z@example.test',
        ]);
        User::factory()->create(['email' => 'other@example.test']);
        $this->makeAdministrator($admin);

        $response = $this->actingAs($admin)->get(route('users.export.xlsx', [
            'search' => 'formula',
            'per_page' => 10,
            'sort' => 'email',
            'direction' => 'desc',
            'columns' => ['email', 'name'],
        ]));

        $response->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertHeader('content-disposition');

        $path = tempnam(sys_get_temp_dir(), 'user-xlsx-test-');
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

        $this->assertSame(['Email', 'Name'], $rows[0]->toArray());
        $this->assertCount(13, $rows);
        $this->assertSame('formula-z@example.test', $rows[1]->cells[0]->getValue());
        $this->assertSame('=HYPERLINK("https://example.test","Click") Member', $rows[1]->cells[1]->getValue());
        $this->assertIsString($sheetXml);
        $this->assertDoesNotMatchRegularExpression('/<f(?:\s|>)/', $sheetXml);
        $this->assertStringContainsString('=HYPERLINK(', $sheetXml);
    }

    public function test_pdf_export_reuses_authorized_filters_and_selected_columns(): void
    {
        $admin = User::factory()->create();
        User::factory()->create([
            'first_name' => 'Report',
            'email' => 'report-a@example.test',
        ]);
        User::factory()->create([
            'first_name' => 'Report',
            'email' => 'report-b@example.test',
        ]);
        User::factory()->create(['email' => 'other@example.test']);
        $this->makeAdministrator($admin);

        $document = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $document->shouldReceive('setPaper')->once()->with('a4', 'landscape')->andReturnSelf();
        $document->shouldReceive('download')->once()->with(\Mockery::type('string'))
            ->andReturn(response('pdf response', 200, ['Content-Type' => 'application/pdf']));
        Pdf::shouldReceive('loadView')
            ->once()
            ->with('exports.users', \Mockery::on(function (array $data): bool {
                $this->assertSame(['Email'], $data['columns']);
                $this->assertSame([
                    ['report-b@example.test'],
                    ['report-a@example.test'],
                ], $data['rows']);
                $this->assertIsString($data['generatedAt']);

                return true;
            }))
            ->andReturn($document);

        $this->actingAs($admin)
            ->get(route('users.export.pdf', [
                'search' => 'Report',
                'sort' => 'email',
                'direction' => 'desc',
                'columns' => ['email'],
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_pdf_endpoint_renders_a_downloadable_document_and_the_view_escapes_html(): void
    {
        $admin = User::factory()->create();
        User::factory()->create([
            'first_name' => '<script>alert(1)</script>',
            'middle_name' => null,
            'last_name' => 'Member',
        ]);
        $this->makeAdministrator($admin);

        $response = $this->actingAs($admin)
            ->get(route('users.export.pdf', ['search' => 'script', 'columns' => ['name']]));

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());

        $html = view('exports.users', [
            'columns' => ['Name'],
            'rows' => [['<script>alert(1)</script>']],
            'generatedAt' => '2026-10-16 12:00:00',
        ])->render();

        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert', $html);
    }

    public function test_pdf_export_rejects_results_above_its_safe_row_limit(): void
    {
        $admin = User::factory()->create();
        User::factory()->count(1000)->create();
        $this->makeAdministrator($admin);

        $this->actingAs($admin)
            ->get(route('users.export.pdf', ['columns' => ['name']]))
            ->assertStatus(422);
    }
}
