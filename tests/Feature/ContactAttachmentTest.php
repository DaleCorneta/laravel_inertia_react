<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\ContactAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ContactAttachmentTest extends TestCase
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
        Storage::fake('local');
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

    public function test_contact_creation_stores_private_attachment_and_download_is_authorized(): void
    {
        $creator = User::factory()->create();
        $viewer = User::factory()->create();
        $this->grant($creator, 'contacts.create');
        $this->grant($viewer, 'contacts.update');

        $this->actingAs($creator)->post('/contacts', [
            'first_name' => 'Ari', 'last_name' => 'Stone',
            'attachments' => [$this->pdf('private-report.pdf')],
        ])->assertRedirect('/contacts');

        $contact = Contact::query()->sole();
        $attachment = ContactAttachment::query()->sole();
        $this->assertSame($contact->id, $attachment->contact_id);
        $this->assertSame($creator->id, $attachment->uploaded_by);
        $this->assertSame('private-report.pdf', $attachment->original_name);
        $this->assertStringStartsWith('contact-attachments/'.$contact->id.'/', $attachment->path);
        $this->assertDatabaseHas('activity_log', ['event' => 'contact.attachment_uploaded', 'subject_id' => $attachment->id]);
        Storage::disk('local')->assertExists($attachment->path);

        $this->actingAs(User::factory()->create())->get(route('contacts.attachments.download', [$contact, $attachment]))->assertForbidden();
        $this->actingAs($viewer)->get(route('contacts.attachments.download', [$contact, $attachment]))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('content-disposition', 'attachment; filename=private-report.pdf');
    }

    public function test_upload_and_delete_require_contact_permissions_and_deletion_removes_private_file(): void
    {
        $contact = Contact::query()->create(['first_name' => 'Ari', 'last_name' => 'Stone']);
        $editor = User::factory()->create();
        $attachment = $this->createAttachment($contact, $editor);

        $this->actingAs(User::factory()->create())->delete(route('contacts.attachments.delete', [$contact, $attachment]))->assertForbidden();
        $this->grant($editor, 'contacts.update');
        $this->actingAs($editor)->delete(route('contacts.attachments.delete', [$contact, $attachment]))->assertRedirect();

        $this->assertDatabaseMissing('contact_attachments', ['id' => $attachment->id]);
        Storage::disk('local')->assertMissing($attachment->path);
        $this->assertDatabaseHas('activity_log', ['event' => 'contact.attachment_deleted', 'subject_id' => $attachment->id]);
    }

    public function test_edit_uploads_add_to_contact_and_attachment_routes_are_scoped_to_that_contact(): void
    {
        $contact = Contact::query()->create(['first_name' => 'Ari', 'last_name' => 'Stone']);
        $otherContact = Contact::query()->create(['first_name' => 'Mina', 'last_name' => 'Lee']);
        $editor = User::factory()->create();
        $this->grant($editor, 'contacts.update');
        $this->grant($editor, 'contacts.view');
        $attachment = $this->createAttachment($contact, $editor);

        $this->actingAs($editor)->post("/contacts/{$contact->id}", [
            '_method' => 'PUT', 'first_name' => 'Ari', 'last_name' => 'Stone', 'attachments' => [$this->pdf('added.pdf')],
        ])->assertRedirect('/contacts');
        $this->assertDatabaseCount('contact_attachments', 2);
        $this->actingAs(User::factory()->create())->get(route('contacts.attachments.download', [$otherContact, $attachment]))->assertForbidden();
        $this->actingAs($editor)->get(route('contacts.attachments.download', [$otherContact, $attachment]))->assertNotFound();
    }

    public function test_upload_validation_rejects_bad_signatures_and_strict_inspection_rejects_active_content(): void
    {
        $creator = User::factory()->create();
        $this->grant($creator, 'contacts.create');
        $this->actingAs($creator)->from('/contacts/create')->post('/contacts', [
            'first_name' => 'Ari', 'last_name' => 'Stone', 'attachments' => [UploadedFile::fake()->createWithContent('fake.pdf', 'not a pdf')],
        ])->assertRedirect('/contacts/create')->assertSessionHasErrors('attachments.0');
        $this->assertDatabaseCount('contacts', 0);

        config()->set('demo.contact_attachments_strict_inspection', true);
        $this->actingAs($creator)->from('/contacts/create')->post('/contacts', [
            'first_name' => 'Ari', 'last_name' => 'Stone', 'attachments' => [$this->pdf('active.pdf', "%PDF-1.7\n<script>alert(1)</script>\n%%EOF")],
        ])->assertRedirect('/contacts/create')->assertSessionHasErrors('attachments.0');
        $this->assertDatabaseCount('contacts', 0);
        $this->assertSame(0, Activity::query()->where('event', 'contact.attachment_uploaded')->count());
    }

    public function test_upload_count_and_per_file_size_limits_are_enforced(): void
    {
        $creator = User::factory()->create();
        $this->grant($creator, 'contacts.create');

        $this->actingAs($creator)->from('/contacts/create')->post('/contacts', [
            'first_name' => 'Ari', 'last_name' => 'Stone',
            'attachments' => array_map(fn (int $index) => $this->pdf("file-$index.pdf"), range(1, 6)),
        ])->assertRedirect('/contacts/create')->assertSessionHasErrors('attachments');

        $this->actingAs($creator)->from('/contacts/create')->post('/contacts', [
            'first_name' => 'Ari', 'last_name' => 'Stone',
            'attachments' => [UploadedFile::fake()->create('oversized.pdf', 10241, 'application/pdf')],
        ])->assertRedirect('/contacts/create')->assertSessionHasErrors('attachments.0');

        $this->assertDatabaseCount('contacts', 0);
        $this->assertDatabaseCount('contact_attachments', 0);
    }

    public function test_png_and_jpeg_uploads_pass_format_aware_validation(): void
    {
        $creator = User::factory()->create();
        $this->grant($creator, 'contacts.create');

        $this->actingAs($creator)->post('/contacts', [
            'first_name' => 'Ari', 'last_name' => 'Stone',
            'attachments' => [UploadedFile::fake()->image('photo.png'), UploadedFile::fake()->image('photo.jpg')],
        ])->assertRedirect('/contacts');

        $this->assertDatabaseCount('contact_attachments', 2);
        $this->assertSame(['image/jpeg', 'image/png'], ContactAttachment::query()->orderBy('mime_type')->pluck('mime_type')->all());
    }

    public function test_permanently_deleting_a_trashed_contact_removes_its_attachments(): void
    {
        $contact = Contact::query()->create(['first_name' => 'Ari', 'last_name' => 'Stone']);
        $contact->delete();
        $attachment = $this->createAttachment($contact, User::factory()->create());
        $user = User::factory()->create();
        $this->grant($user, 'contacts.force-delete');

        $this->actingAs($user)->delete(route('contacts.force-destroy', $contact))->assertRedirect();
        $this->assertDatabaseMissing('contacts', ['id' => $contact->id]);
        $this->assertDatabaseMissing('contact_attachments', ['id' => $attachment->id]);
        Storage::disk('local')->assertMissing($attachment->path);
    }

    private function createAttachment(Contact $contact, User $uploader): ContactAttachment
    {
        $file = $this->pdf('saved.pdf');
        $path = $file->store('contact-attachments/'.$contact->id, 'local');

        return $contact->attachments()->create([
            'uploaded_by' => $uploader->id,
            'original_name' => 'saved.pdf',
            'path' => $path,
            'mime_type' => 'application/pdf',
            'size' => $file->getSize(),
        ]);
    }

    private function pdf(string $name, string $contents = "%PDF-1.7\n1 0 obj << >>\nendobj\n%%EOF"): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $contents);
    }

    private function grant(User $user, string $permission): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate($permission, 'web');
        $user->givePermissionTo($permission);
    }
}
