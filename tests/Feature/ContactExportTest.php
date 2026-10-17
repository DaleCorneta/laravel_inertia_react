<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ContactExportTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, array{env: string|null, server: string|null, process: string|false}> */
    private array $originalFlags = [];

    protected function setUp(): void
    {
        foreach (['DEMO_MODULES_ENABLED', 'DEMO_CONTACT_DIRECTORY_ENABLED'] as $key) {
            $this->originalFlags[$key] = ['env' => $_ENV[$key] ?? null, 'server' => $_SERVER[$key] ?? null, 'process' => getenv($key)];
            $_ENV[$key] = $_SERVER[$key] = 'true';
            putenv($key.'=true');
        }
        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        foreach ($this->originalFlags as $key => $values) {
            foreach (['env' => '_ENV', 'server' => '_SERVER'] as $source => $target) {
                if ($values[$source] === null) {
                    unset($GLOBALS[$target][$key]);
                } else {
                    $GLOBALS[$target][$key] = $values[$source];
                }
            }
            $values['process'] === false ? putenv($key) : putenv($key.'='.$values['process']);
        }
    }

    public function test_contact_exports_require_authentication_and_view_permission(): void
    {
        foreach (['contacts.export', 'contacts.export.xlsx', 'contacts.export.pdf'] as $route) {
            $this->get(route($route, ['columns' => ['name']]))->assertRedirect(route('login'));
        }
        $user = User::factory()->create();
        foreach (['contacts.export', 'contacts.export.xlsx', 'contacts.export.pdf'] as $route) {
            $this->actingAs($user)->get(route($route, ['columns' => ['name']]))->assertForbidden();
        }
    }

    public function test_csv_export_preserves_filters_columns_and_neutralizes_formulas(): void
    {
        $user = User::factory()->create();
        $this->grant($user, 'contacts.view');
        Contact::query()->create(['first_name' => '=HYPERLINK("https://example.test","Click")', 'last_name' => 'Needle', 'email' => '=formula@example.test']);
        Contact::query()->create(['first_name' => 'Other', 'last_name' => 'Person', 'email' => 'other@example.test']);

        $response = $this->actingAs($user)->get(route('contacts.export', [
            'search' => 'needle', 'sort' => 'email', 'direction' => 'desc', 'columns' => ['email', 'name'],
        ]))->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $rows = array_map(static fn (string $line): array => str_getcsv($line), explode("\r\n", trim(substr($response->streamedContent(), 3))));

        $this->assertSame(['Email', 'Name'], $rows[0]);
        $this->assertSame("'=formula@example.test", $rows[1][0]);
        $this->assertSame("'=HYPERLINK(\"https://example.test\",\"Click\") Needle", $rows[1][1]);
        $this->actingAs($user)->get(route('contacts.export', ['columns' => ['notes']]))->assertSessionHasErrors('columns.0');
    }

    public function test_xlsx_export_writes_selected_values_as_plain_text(): void
    {
        $user = User::factory()->create();
        $this->grant($user, 'contacts.view');
        Contact::query()->create(['first_name' => '=formula', 'last_name' => 'Contact', 'email' => 'formula@example.test']);
        $response = $this->actingAs($user)->get(route('contacts.export.xlsx', ['columns' => ['email', 'name']]))->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'contact-xlsx-test-');
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
            unset($sheets);
            $reader->close();
            unset($reader);
            gc_collect_cycles();
            if (is_file($path)) {
                unlink($path);
            }
        }
        $this->assertSame(['Email', 'Name'], $rows[0]->toArray());
        $this->assertSame('formula@example.test', $rows[1]->cells[0]->getValue());
        $this->assertSame('=formula Contact', $rows[1]->cells[1]->getValue());
        $this->assertIsString($sheetXml);
        $this->assertDoesNotMatchRegularExpression('/<f(?:\s|>)/', $sheetXml);
    }

    public function test_trash_exports_need_trash_permission_and_pdf_uses_selected_columns(): void
    {
        $user = User::factory()->create();
        $this->grant($user, 'contacts.view');
        $trashed = Contact::query()->create(['first_name' => 'Trash', 'last_name' => 'Needle', 'email' => 'trash@example.test']);
        $trashed->delete();
        Contact::query()->create(['first_name' => 'Active', 'last_name' => 'Needle', 'email' => 'active@example.test']);
        $params = ['view' => 'trash', 'search' => 'needle', 'columns' => ['email']];
        $this->actingAs($user)->get(route('contacts.export', $params))->assertForbidden();
        $this->grant($user, 'contacts.view_trash');
        $csv = $this->actingAs($user)->get(route('contacts.export', $params))->streamedContent();
        $this->assertStringContainsString('trash@example.test', $csv);
        $this->assertStringNotContainsString('active@example.test', $csv);

        $document = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $document->shouldReceive('setPaper')->once()->with('a4', 'landscape')->andReturnSelf();
        $document->shouldReceive('download')->once()->with(\Mockery::type('string'))->andReturn(response('pdf response', 200, ['Content-Type' => 'application/pdf']));
        Pdf::shouldReceive('loadView')->once()->with('exports.contacts', \Mockery::on(function (array $data): bool {
            $this->assertSame(['Email'], $data['columns']);
            $this->assertSame([['trash@example.test']], $data['rows']);

            return true;
        }))->andReturn($document);
        $this->actingAs($user)->get(route('contacts.export.pdf', $params))->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    private function grant(User $user, string $permission): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate($permission, 'web');
        $user->givePermissionTo($permission);
    }
}
