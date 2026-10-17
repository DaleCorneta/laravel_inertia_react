<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\Cursor;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class UserIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('users.index'))->assertRedirect(route('login'));
    }

    public function test_user_without_users_view_access_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('users.index'))
            ->assertForbidden();
    }

    public function test_authorized_user_can_search_and_page_the_user_list(): void
    {
        $admin = User::factory()->create([
            'first_name' => 'Workspace',
            'last_name' => 'Admin',
        ]);
        $matching = User::factory()->create([
            'first_name' => 'Caroline',
            'last_name' => 'Rivera',
            'email' => 'caroline.rivera@example.test',
        ]);
        User::factory()->count(10)->create();
        $this->makeAdministrator($admin);

        $this->actingAs($admin)
            ->get(route('users.index', ['search' => 'Caroline', 'per_page' => 10]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Users/Index')
                ->where('auth.user.can_manage_users', true)
                ->where('filters.search', 'Caroline')
                ->where('filters.per_page', 10)
                ->where('users.total', 1)
                ->where('users.data.0.id', $matching->id)
                ->where('users.data.0.full_name', $matching->full_name)
                ->missing('users.data.0.password'));
    }

    public function test_authorized_user_receives_server_pagination_with_a_validated_page_size(): void
    {
        $admin = User::factory()->create();
        User::factory()->count(11)->create();
        $this->makeAdministrator($admin);

        $this->actingAs($admin)
            ->get(route('users.index', ['per_page' => 10]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('users.data', fn ($data) => $data->count() === 10)
                ->where('users.total', 12)
                ->where('users.last_page', 2));
    }

    public function test_all_mode_loads_users_in_cursor_fetched_batches(): void
    {
        $admin = User::factory()->create();
        User::factory()->count(30)->create([
            'first_name' => 'Batch',
            'last_name' => 'Member',
        ]);
        $this->makeAdministrator($admin);
        $cursor = null;

        $this->actingAs($admin)
            ->get(route('users.index', ['search' => 'Batch', 'per_page' => 'all']))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) use (&$cursor) {
                return $page
                    ->component('Users/Index')
                    ->where('filters.per_page', 'all')
                    ->where('users.total', 30)
                    ->where('users.data', fn ($data) => $data->count() === 25)
                    ->where('users.has_more', true)
                    ->where('users.next_cursor', function ($value) use (&$cursor) {
                        $cursor = $value;

                        return is_string($value) && $value !== '';
                    });
            });

        $batchUrl = route('users.batches').'?'.http_build_query([
            'search' => 'Batch',
            'sort' => 'name',
            'direction' => 'asc',
            'cursor' => $cursor,
        ]);
        $decodedCursor = Cursor::fromEncoded($cursor);
        $this->assertNotNull($decodedCursor);
        $this->assertTrue($decodedCursor->pointsToNextItems());

        $this->getJson($batchUrl)
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('has_more', false)
            ->assertJsonPath('next_cursor', null);
    }

    public function test_user_without_access_cannot_fetch_a_user_batch(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('users.batches'))
            ->assertForbidden();
    }
}
