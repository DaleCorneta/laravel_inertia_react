<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ContactDirectoryTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, array{env: string|null, server: string|null, process: string|false}> */
    private array $originalFlags = [];

    protected function setUp(): void
    {
        foreach (['DEMO_MODULES_ENABLED', 'DEMO_CONTACT_DIRECTORY_ENABLED'] as $key) {
            $this->originalFlags[$key] = [
                'env' => $_ENV[$key] ?? null,
                'server' => $_SERVER[$key] ?? null,
                'process' => getenv($key),
            ];
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

    public function test_contacts_require_authentication_and_specific_permissions(): void
    {
        $this->get('/contacts')->assertRedirect('/login');
        $this->get('/contacts/create')->assertRedirect('/login');

        $user = User::factory()->create();
        $this->actingAs($user)->get('/contacts')->assertForbidden();
        $this->actingAs($user)->get('/contacts/create')->assertForbidden();
        $this->actingAs($user)->post('/contacts', ['first_name' => 'Ari', 'last_name' => 'Stone'])->assertForbidden();
        $this->assertDatabaseCount('contacts', 0);
    }

    public function test_authorized_user_can_search_and_page_contacts(): void
    {
        $user = User::factory()->create();
        $this->grant($user, 'contacts.view');
        Contact::query()->create(['first_name' => 'Ari', 'last_name' => 'Stone', 'organization' => 'Northwind']);
        Contact::query()->create(['first_name' => 'Mina', 'last_name' => 'Lee', 'organization' => 'Contoso']);

        $this->actingAs($user)->get('/contacts?search=northwind')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Contacts/Index')
                ->where('filters.search', 'northwind')
                ->where('contacts.total', 1)
                ->where('contacts.data.0.full_name', 'Ari Stone'));
    }

    public function test_authorized_user_can_create_a_contact_and_audit_only_field_names(): void
    {
        $user = User::factory()->create();
        $this->grant($user, 'contacts.create');

        $this->actingAs($user)->post('/contacts', [
            'prefix' => '  Dr. ',
            'first_name' => '  Ari ',
            'middle_name' => '  Q. ',
            'last_name' => ' Stone  ',
            'organization' => ' Northwind ',
            'title' => ' Designer ',
            'email' => ' ARI@EXAMPLE.TEST ',
            'phone' => ' 555-0100 ',
            'notes' => 'Private note',
        ])->assertRedirect('/contacts');

        $contact = Contact::query()->sole();
        $this->assertSame('Ari', $contact->first_name);
        $this->assertSame('ari@example.test', $contact->email);
        $this->assertSame($user->id, $contact->owner_id);
        $this->assertDatabaseHas('activity_log', ['event' => 'contact.created', 'subject_id' => $contact->id]);
        $activity = Activity::query()->where('event', 'contact.created')->sole();
        $this->assertContains('email', $activity->properties['fields']);
        $this->assertNotContains('ari@example.test', $activity->properties->toArray());
    }

    public function test_contact_creation_validates_structured_name_and_email(): void
    {
        $user = User::factory()->create();
        $this->grant($user, 'contacts.create');

        $this->actingAs($user)->from('/contacts/create')->post('/contacts', [
            'first_name' => '',
            'last_name' => '',
            'email' => 'invalid',
        ])->assertRedirect('/contacts/create')->assertSessionHasErrors(['first_name', 'last_name', 'email']);

        $this->assertDatabaseCount('contacts', 0);
    }

    public function test_contact_updates_require_permission_preserve_owner_and_audit_only_changed_fields(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $contact = Contact::query()->create([
            'owner_id' => $owner->id,
            'first_name' => 'Ari',
            'last_name' => 'Stone',
            'email' => 'ari@example.test',
        ]);

        $this->actingAs($editor)->get("/contacts/{$contact->id}/edit")->assertForbidden();
        $this->actingAs($editor)->put("/contacts/{$contact->id}", [
            'first_name' => 'Updated',
            'last_name' => 'Stone',
        ])->assertForbidden();

        $this->grant($editor, 'contacts.update');
        $this->actingAs($editor)->get("/contacts/{$contact->id}/edit")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Contacts/Edit')
                ->where('contact.id', $contact->id)
                ->where('contact.first_name', 'Ari'));

        $this->actingAs($editor)->put("/contacts/{$contact->id}", [
            'first_name' => 'Updated',
            'last_name' => 'Stone',
            'email' => ' UPDATED@EXAMPLE.TEST ',
            'owner_id' => $editor->id,
        ])->assertRedirect('/contacts');

        $contact->refresh();
        $this->assertSame('Updated', $contact->first_name);
        $this->assertSame('updated@example.test', $contact->email);
        $this->assertSame($owner->id, $contact->owner_id);
        $activity = Activity::query()->where('event', 'contact.updated')->sole();
        $auditedFields = $activity->properties['fields'];
        sort($auditedFields);
        $this->assertSame(['email', 'first_name'], $auditedFields);
        $this->assertNotContains('updated@example.test', $activity->properties->toArray());
    }

    public function test_archive_is_separate_from_trash_and_trash_can_be_restored(): void
    {
        $user = User::factory()->create();
        $contact = Contact::query()->create(['first_name' => 'Ari', 'last_name' => 'Stone']);

        $this->actingAs($user)->patch("/contacts/{$contact->id}/archive")->assertForbidden();
        $this->actingAs($user)->delete("/contacts/{$contact->id}")->assertForbidden();

        $this->grant($user, 'contacts.archive');
        $this->from('/contacts')->actingAs($user)->patch("/contacts/{$contact->id}/archive")->assertRedirect('/contacts');
        $contact->refresh();
        $this->assertNotNull($contact->archived_at);
        $this->assertNull($contact->deleted_at);
        $this->assertDatabaseHas('activity_log', ['event' => 'contact.archived', 'subject_id' => $contact->id]);

        $this->from('/contacts')->actingAs($user)->patch("/contacts/{$contact->id}/unarchive")->assertRedirect('/contacts');
        $contact->refresh();
        $this->assertNull($contact->archived_at);
        $this->assertNull($contact->deleted_at);

        $this->grant($user, 'contacts.delete');
        $this->from('/contacts')->actingAs($user)->delete("/contacts/{$contact->id}")->assertRedirect('/contacts');
        $this->assertSoftDeleted('contacts', ['id' => $contact->id]);
        $this->actingAs($user)->get('/contacts?view=trash')->assertForbidden();

        $this->grant($user, 'contacts.view');
        $this->grant($user, 'contacts.view_trash');
        $this->actingAs($user)->get('/contacts?view=trash')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Contacts/Index')
                ->where('filters.view', 'trash')
                ->where('contacts.total', 1));

        $this->grant($user, 'contacts.restore');
        $this->from('/contacts?view=trash')->actingAs($user)->post("/contacts/{$contact->id}/restore")->assertRedirect('/contacts?view=trash');
        $this->assertNotSoftDeleted('contacts', ['id' => $contact->id]);
        $this->assertDatabaseHas('activity_log', ['event' => 'contact.trashed', 'subject_id' => $contact->id]);
        $this->assertDatabaseHas('activity_log', ['event' => 'contact.restored', 'subject_id' => $contact->id]);
    }

    public function test_permanent_delete_requires_trashed_record_and_force_delete_permission(): void
    {
        $user = User::factory()->create();
        $contact = Contact::query()->create(['first_name' => 'Ari', 'last_name' => 'Stone']);
        $this->grant($user, 'contacts.force-delete');

        $this->actingAs($user)->delete("/contacts/{$contact->id}/permanent")->assertNotFound();
        $contact->delete();
        $this->from('/contacts?view=trash')->actingAs($user)->delete("/contacts/{$contact->id}/permanent")->assertRedirect('/contacts?view=trash');

        $this->assertDatabaseMissing('contacts', ['id' => $contact->id]);
        $this->assertDatabaseHas('activity_log', ['event' => 'contact.force_deleted', 'subject_id' => $contact->id]);
    }

    private function grant(User $user, string $permission): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate($permission, 'web');
        $user->givePermissionTo($permission);
    }
}
