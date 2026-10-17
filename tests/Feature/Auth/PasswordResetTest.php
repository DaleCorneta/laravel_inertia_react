<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const GENERIC_STATUS = 'If an account exists for that email, a password reset link has been sent.';

    public function test_guest_can_view_the_forgot_password_page(): void
    {
        $this->get('/forgot-password')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Auth/ForgotPassword'));
    }

    public function test_existing_account_receives_a_reset_notification_with_generic_status(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->from('/forgot-password')->post('/forgot-password', [
            'email' => $user->email,
        ])->assertRedirect('/forgot-password')->assertSessionHas('status', self::GENERIC_STATUS);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_unknown_email_receives_the_same_generic_status_without_a_notification(): void
    {
        Notification::fake();

        $this->from('/forgot-password')->post('/forgot-password', [
            'email' => 'unknown@example.com',
        ])->assertRedirect('/forgot-password')->assertSessionHas('status', self::GENERIC_STATUS);

        Notification::assertNothingSent();
    }

    public function test_reset_link_opens_the_reset_page_with_its_email_and_token(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Auth/ResetPassword')
                ->where('email', $user->email)
                ->where('token', $token));
    }

    public function test_valid_reset_changes_the_password_and_returns_to_login(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);
        config(['session.driver' => 'database']);
        DB::table('sessions')->insert([
            'id' => 'other-session',
            'user_id' => $user->id,
            'payload' => base64_encode(serialize([])),
            'last_activity' => time(),
        ]);

        $this->post('/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertRedirectToRoute('login')->assertSessionHas('status', 'Your password has been reset. You can now sign in.');

        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'other-session']);
    }

    public function test_invalid_reset_token_does_not_change_the_password(): void
    {
        $user = User::factory()->create();
        $originalPassword = $user->password;

        $this->from(route('password.reset', ['token' => 'invalid-token', 'email' => $user->email]))
            ->post('/reset-password', [
                'email' => $user->email,
                'token' => 'invalid-token',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertRedirect(route('password.reset', ['token' => 'invalid-token', 'email' => $user->email]))
            ->assertSessionHasErrors('email');

        $this->assertSame($originalPassword, $user->fresh()->password);
    }

    public function test_password_reset_requests_are_limited_to_five_per_email_and_ip_per_minute(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->from('/forgot-password')->post('/forgot-password', [
                'email' => 'unknown@example.com',
            ])->assertRedirect('/forgot-password');
        }

        $this->post('/forgot-password', [
            'email' => 'unknown@example.com',
        ])->assertTooManyRequests();
    }
}
