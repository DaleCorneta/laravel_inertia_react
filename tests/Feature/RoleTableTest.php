<?php

namespace Tests\Feature;

use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_table_and_exports_require_authentication_and_roles_view_permission(): void
    {
        foreach (['roles.index', 'roles.batches'] as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }
        foreach (['roles.export', 'roles.export.xlsx', 'roles.export.pdf'] as $route) {
            $this->get(route($route, ['columns' => ['name']]))->assertRedirect(route('login'));
        }

        $this->actingAs(User::factory()->create());
        foreach (['roles.index', 'roles.batches'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
        foreach (['roles.export', 'roles.export.xlsx', 'roles.export.pdf'] as $route) {
            $this->get(route($route, ['columns' => ['name']]))->assertForbidden();
        }
    }

    public function test_authorized_role_table_searches_sorts_and_paginates_server_side(): void
    {
        $viewer = $this->makeRoleViewer();
        for ($index = 1; $index <= 11; $index++) {
            Role::create(['name' => 'Support '.$index, 'guard_name' => 'web']);
        }
        Role::create(['name' => 'Other role', 'guard_name' => 'web']);

        $this->actingAs($viewer)->get(route('roles.index', [
            'search' => 'Support', 'per_page' => 10, 'sort' => 'name', 'direction' => 'desc',
        ]))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Roles/Index')
            ->where('filters.search', 'Support')
            ->where('filters.per_page', 10)
            ->where('filters.direction', 'desc')
            ->where('roles.total', 11)
            ->has('roles.data', 10)
            ->where('roles.data.0.name', 'Support 9')
            ->where('roles.data.0.type', 'custom')
            ->has('roles.links')
        );
    }

    public function test_all_mode_loads_roles_in_sequential_cursor_batches(): void
    {
        $viewer = $this->makeRoleViewer();
        for ($index = 1; $index <= 28; $index++) {
            Role::create(['name' => sprintf('Role %02d', $index), 'guard_name' => 'web']);
        }

        $this->actingAs($viewer)->get(route('roles.index', ['per_page' => 'all']))
            ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('roles.total', 28)
            ->has('roles.data', 25)
            ->where('roles.has_more', true)
            ->where('filters.per_page', 'all')
            );

        $firstPage = $this->get(route('roles.index', ['per_page' => 'all']))->original->getData()['page']['props']['roles'];
        $this->getJson(route('roles.batches', [
            'cursor' => $firstPage['next_cursor'], 'sort' => 'name', 'direction' => 'asc',
        ]))->assertOk()->assertJsonCount(3, 'data')->assertJsonPath('has_more', false);
    }

    public function test_csv_export_preserves_filters_selected_columns_and_neutralizes_formulas(): void
    {
        $viewer = $this->makeRoleViewer();
        Role::create(['name' => '=HYPERLINK("https://example.test","Click")', 'guard_name' => 'web']);
        Role::create(['name' => 'Other role', 'guard_name' => 'web']);

        $response = $this->actingAs($viewer)->get(route('roles.export', [
            'search' => 'HYPERLINK', 'columns' => ['name', 'type'],
        ]));
        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = substr($response->streamedContent(), 3);
        $rows = array_map(static fn (string $line): array => str_getcsv($line), explode("\r\n", trim($csv)));
        $this->assertSame(['Role', 'Type'], $rows[0]);
        $this->assertSame("'=HYPERLINK(\"https://example.test\",\"Click\")", $rows[1][0]);
        $this->assertSame('Custom role', $rows[1][1]);
        $this->actingAs($viewer)->get(route('roles.export', ['columns' => ['password']]))->assertSessionHasErrors('columns.0');
    }

    public function test_xlsx_exports_selected_cells_as_strings_without_formula_cells(): void
    {
        $viewer = $this->makeRoleViewer();
        Role::create(['name' => '=HYPERLINK("https://example.test","Click")', 'guard_name' => 'web']);
        $response = $this->actingAs($viewer)->get(route('roles.export.xlsx', ['columns' => ['name']]));
        $path = tempnam(sys_get_temp_dir(), 'role-xlsx-test-');
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
        $this->assertSame(['Role'], $rows[0]->toArray());
        $this->assertSame('=HYPERLINK("https://example.test","Click")', $rows[1]->cells[0]->getValue());
        $this->assertDoesNotMatchRegularExpression('/<f(?:\s|>)/', $sheetXml);
    }

    public function test_pdf_uses_filtered_selected_data_escapes_html_and_enforces_row_limit(): void
    {
        $viewer = $this->makeRoleViewer();
        Role::create(['name' => '<script>Needle</script>', 'guard_name' => 'web']);
        Role::create(['name' => 'Other role', 'guard_name' => 'web']);
        $document = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $document->shouldReceive('setPaper')->once()->with('a4', 'landscape')->andReturnSelf();
        $document->shouldReceive('download')->once()->with(\Mockery::type('string'))
            ->andReturn(response('pdf response', 200, ['Content-Type' => 'application/pdf']));
        Pdf::shouldReceive('loadView')->once()->with('exports.roles', \Mockery::on(function (array $data): bool {
            $this->assertSame(['Role'], $data['columns']);
            $this->assertSame([['<script>Needle</script>']], $data['rows']);

            return true;
        }))->andReturn($document);
        $this->actingAs($viewer)->get(route('roles.export.pdf', ['search' => 'Needle', 'columns' => ['name']]))
            ->assertOk()->assertHeader('content-type', 'application/pdf');

        $html = view('exports.roles', ['columns' => ['Role'], 'rows' => [['<script>alert(1)</script>']], 'generatedAt' => '2026-10-16 12:00:00'])->render();
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert', $html);

        for ($index = 0; $index < 1001; $index++) {
            Role::create(['name' => 'Limit '.$index, 'guard_name' => 'web']);
        }
        $this->actingAs($viewer)->get(route('roles.export.pdf', ['columns' => ['name']]))->assertStatus(422);
    }

    private function makeRoleViewer(): User
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(Permission::findOrCreate('roles.view', 'web'));

        return $viewer;
    }
}
