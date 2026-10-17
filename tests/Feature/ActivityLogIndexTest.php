<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ActivityLogIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_and_user_without_audit_permission_cannot_view_activity_logs(): void
    {
        $this->get(route('activity-logs.index'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())
            ->get(route('activity-logs.index'))
            ->assertForbidden();
    }

    public function test_authorized_user_can_search_filter_and_page_redacted_activity(): void
    {
        $viewer = User::factory()->create([
            'first_name' => 'Morgan',
            'middle_name' => null,
            'last_name' => 'Auditor',
        ]);
        $viewer->givePermissionTo(Permission::findOrCreate('activity-logs.view', 'web'));
        $subject = User::factory()->create([
            'first_name' => 'Jordan',
            'middle_name' => null,
            'last_name' => 'Member',
            'email' => 'private@example.test',
        ]);
        $this->actingAs($viewer);

        for ($index = 0; $index < 11; $index++) {
            app(AuditLogger::class)->log('user.created', $subject, ['fields' => ['email', 'first_name']]);
        }

        Activity::query()->create([
            'log_name' => 'audit',
            'description' => 'auth.login_failed',
            'event' => 'auth.login_failed',
            'properties' => [
                'email' => 'must-not-appear@example.test',
                'password' => 'MustNotAppear123!',
                'fields' => ['email', 'password'],
            ],
        ]);

        $this->actingAs($viewer)
            ->get(route('activity-logs.index', [
                'search' => 'Jordan',
                'event' => 'user.created',
                'per_page' => 10,
                'page' => 1,
            ]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('ActivityLogs/Index')
                ->where('filters.search', 'Jordan')
                ->where('filters.event', 'user.created')
                ->where('activities.total', 11)
                ->where('activities.data', fn ($data) => $data->count() === 10)
                ->where('activities.data.0.action', 'User Created')
                ->where('activities.data.0.actor', 'Morgan Auditor')
                ->where('activities.data.0.subject', 'Jordan Member')
                ->where('activities.data.0.changes', ['Fields: First Name, Email'])
                ->missing('activities.data.0.email')
                ->missing('activities.data.0.password')
                ->missing('activities.data.0.properties'));

        $this->get(route('activity-logs.index', ['event' => 'auth.login_failed']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('activities.total', 1)
                ->where('activities.data.0.action', 'Auth Login Failed')
                ->where('activities.data.0.changes', ['Fields: Email'])
                ->missing('activities.data.0.properties')
                ->missing('activities.data.0.password'));
    }

    public function test_event_filter_rejects_values_outside_the_audit_event_allowlist(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(Permission::findOrCreate('activity-logs.view', 'web'));

        $this->actingAs($viewer)
            ->from(route('activity-logs.index'))
            ->get(route('activity-logs.index', ['event' => 'private.event']))
            ->assertSessionHasErrors('event');
    }
}
