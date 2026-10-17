<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class UserEditingTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_and_unauthorized_user_cannot_view_or_update_an_account(): void
    {
        $person = User::factory()->create();
        $this->get(route('users.show', $person))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())
            ->get(route('users.show', $person))
            ->assertForbidden();

        $this->put(route('users.update', $person), $this->userData($person))
            ->assertForbidden();
    }

    public function test_authorized_user_can_view_and_edit_a_profile_without_reverifying_an_unchanged_email(): void
    {
        Notification::fake();
        $admin = User::factory()->create();
        $person = User::factory()->create([
            'prefix' => 'Dr.',
            'first_name' => 'Riley',
            'middle_name' => 'Morgan',
            'last_name' => 'Lee',
            'suffix' => null,
            'email' => 'riley.lee@example.test',
        ]);
        $this->makeAdministrator($admin);

        $this->actingAs($admin)
            ->get(route('users.show', $person))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Users/Show')
                ->where('person.id', $person->id)
                ->where('person.full_name', 'Dr. Riley Morgan Lee')
                ->where('person.email_verified', true));

        $this->get(route('users.edit', $person))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Users/Edit')
                ->where('person.email', $person->email));

        $this->put(route('users.update', $person), [
            ...$this->userData($person),
            'first_name' => 'Riley-Ann',
            'email' => ' RILEY.LEE@EXAMPLE.TEST ',
        ])
            ->assertRedirect(route('users.show', $person))
            ->assertSessionHas('status', 'User updated.');

        $this->assertDatabaseHas('users', [
            'id' => $person->id,
            'first_name' => 'Riley-Ann',
            'email' => 'riley.lee@example.test',
        ]);
        $this->assertNotNull($person->fresh()->email_verified_at);
        Notification::assertNothingSent();
    }

    public function test_changing_email_clears_verification_and_sends_notification_to_new_email(): void
    {
        Notification::fake();
        $admin = User::factory()->create();
        $person = User::factory()->create(['email' => 'before@example.test']);
        $this->makeAdministrator($admin);

        $this->actingAs($admin)
            ->put(route('users.update', $person), [
                ...$this->userData($person),
                'email' => ' NEW.ADDRESS@EXAMPLE.TEST ',
            ])
            ->assertRedirect(route('users.show', $person))
            ->assertSessionHas('status', 'User updated. A verification email has been sent to the new address.');

        $updated = $person->fresh();
        $this->assertSame('new.address@example.test', $updated->email);
        $this->assertNull($updated->email_verified_at);
        Notification::assertSentTo($updated, VerifyEmail::class);
    }

    public function test_duplicate_email_and_missing_required_names_are_rejected(): void
    {
        $admin = User::factory()->create();
        $person = User::factory()->create();
        User::factory()->create(['email' => 'already.used@example.test']);
        $this->makeAdministrator($admin);

        $this->actingAs($admin)
            ->from(route('users.edit', $person))
            ->put(route('users.update', $person), [
                ...$this->userData($person),
                'first_name' => '',
                'last_name' => '',
                'email' => 'already.used@example.test',
            ])
            ->assertSessionHasErrors(['first_name', 'last_name', 'email']);

        $this->assertSame($person->first_name, $person->fresh()->first_name);
    }

    /** @return array<string, string|null> */
    private function userData(User $person): array
    {
        return [
            'prefix' => $person->prefix,
            'first_name' => $person->first_name,
            'middle_name' => $person->middle_name,
            'last_name' => $person->last_name,
            'suffix' => $person->suffix,
            'email' => $person->email,
        ];
    }
}
