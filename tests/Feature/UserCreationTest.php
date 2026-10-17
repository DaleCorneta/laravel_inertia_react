<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class UserCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_the_create_page(): void
    {
        $this->get(route('users.create'))->assertRedirect(route('login'));
    }

    public function test_user_without_create_access_cannot_open_or_submit_the_form(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('users.create'))
            ->assertForbidden();

        $this->post(route('users.store'), $this->validUserData())
            ->assertForbidden();
    }

    public function test_authorized_user_can_create_an_unverified_account_and_send_verification(): void
    {
        Notification::fake();
        $admin = User::factory()->create();
        $this->makeAdministrator($admin);

        $this->actingAs($admin)
            ->get(route('users.create'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Users/Create')
                ->where('auth.user.can_create_users', true));

        $this->post(route('users.store'), $this->validUserData())
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('status', 'User account created. A verification email has been sent.');

        $created = User::where('email', 'new.member@example.test')->firstOrFail();
        $this->assertSame('Jamie Quinn Taylor Jr.', $created->full_name);
        $this->assertNull($created->email_verified_at);
        $this->assertTrue(Hash::check('StrongTemporaryPass123!', $created->password));
        $this->assertNotSame('StrongTemporaryPass123!', $created->password);
        Notification::assertSentTo($created, VerifyEmail::class);
    }

    public function test_duplicate_email_and_invalid_password_are_rejected(): void
    {
        $admin = User::factory()->create();
        $existing = User::factory()->create(['email' => 'taken@example.test']);
        $this->makeAdministrator($admin);

        $this->actingAs($admin)
            ->from(route('users.create'))
            ->post(route('users.store'), [
                ...$this->validUserData(),
                'email' => $existing->email,
                'password_confirmation' => 'different-password',
            ])
            ->assertSessionHasErrors(['email', 'password']);

        $this->assertDatabaseCount('users', 2);
    }

    /** @return array<string, string> */
    private function validUserData(): array
    {
        return [
            'prefix' => '',
            'first_name' => 'Jamie',
            'middle_name' => 'Quinn',
            'last_name' => 'Taylor',
            'suffix' => 'Jr.',
            'email' => ' New.Member@Example.Test ',
            'password' => 'StrongTemporaryPass123!',
            'password_confirmation' => 'StrongTemporaryPass123!',
        ];
    }
}
