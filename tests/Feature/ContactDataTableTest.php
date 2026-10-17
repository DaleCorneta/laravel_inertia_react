<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ContactDataTableTest extends TestCase
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

    public function test_contact_table_supports_filtered_sorted_pages_and_cursor_batches(): void
    {
        $user = User::factory()->create();
        $this->grant($user, 'contacts.view');
        for ($index = 1; $index <= 27; $index++) {
            Contact::query()->create(['first_name' => 'Contact '.$index, 'last_name' => 'Member', 'organization' => $index === 1 ? null : sprintf('Company %02d', $index)]);
        }

        $this->actingAs($user)->get('/contacts?search=member&per_page=all&sort=organization&direction=desc')
            ->assertOk()
            ->assertInertia(fn ($response) => $response
                ->where('filters.per_page', 'all')
                ->where('filters.sort', 'organization')
                ->where('contacts.total', 27)
                ->has('contacts.data', 25)
                ->where('contacts.has_more', true));
        $cursor = Contact::query()->select('contacts.*')->selectRaw("COALESCE(organization, '') as organization_sort")
            ->orderBy('organization_sort', 'desc')->orderBy('id')->cursorPaginate(25)->nextCursor()->encode();

        $this->get('/contacts/batches?'.http_build_query([
            'search' => 'member', 'view' => 'active', 'sort' => 'organization', 'direction' => 'desc', 'cursor' => $cursor,
        ]))->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('has_more', false);
    }

    private function grant(User $user, string $permission): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate($permission, 'web');
        $user->givePermissionTo($permission);
    }
}
