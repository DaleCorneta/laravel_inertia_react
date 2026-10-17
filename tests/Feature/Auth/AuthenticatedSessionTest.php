<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AuthenticatedSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_the_login_page(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Auth/Login'));
    }

    public function test_user_can_log_in_and_receive_minimal_shared_auth_data(): void
    {
        $user = User::factory()->create();

        $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);

        $this->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Welcome')
                ->where('auth.user.id', $user->id)
                ->where('auth.user.first_name', $user->first_name)
                ->where('auth.user.display_name', $user->display_name)
                ->where('auth.user.email', $user->email)
                ->missing('auth.user.password'));
    }

    public function test_mfa_user_must_complete_the_challenge_before_login(): void
    {
        config(['auth.mfa_enabled' => true]);
        $user = $this->mfaUser();

        $loginResponse = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'remember' => true,
        ])->assertRedirect(route('login'));

        $this->assertGuest();
        $loginResponse->assertSessionHas('auth.mfa.pending.user_id', $user->id)
            ->assertSessionMissing('auth.mfa.pending.password');
        $this->get(route('login'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Auth/Login')
                ->where('mfaChallenge', true));

        $invalidCode = '000000';
        while (in_array($invalidCode, [$user->makeTwoFactorCode(), $user->makeTwoFactorCode('now', -1)], true)) {
            $invalidCode = str_pad((string) ((int) $invalidCode + 1), 6, '0', STR_PAD_LEFT);
        }

        $this->from('/login')->post(route('login.mfa'), ['code' => $invalidCode])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('code');
        $this->assertGuest();

        $this->post(route('login.mfa'), ['code' => $user->makeTwoFactorCode()])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_mfa_recovery_code_can_be_used_once_to_complete_login(): void
    {
        config(['auth.mfa_enabled' => true]);
        $user = $this->mfaUser();
        $recoveryCode = $user->getRecoveryCodes()->first()['code'];

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('login'));
        $this->post(route('login.mfa'), ['code' => $recoveryCode])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        $this->delete(route('logout'))->assertRedirect(route('login'));
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('login'));
        $this->post(route('login.mfa'), ['code' => $recoveryCode])
            ->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_mfa_disabled_allows_password_login_for_an_mfa_account(): void
    {
        config(['auth.mfa_enabled' => false]);
        $user = $this->mfaUser();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'))
            ->assertSessionMissing('auth.mfa.pending');

        $this->assertAuthenticatedAs($user);
    }

    public function test_expired_mfa_challenge_returns_to_password_login(): void
    {
        config(['auth.mfa_enabled' => true]);
        $user = $this->mfaUser();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('login'));
        $this->travel(6)->minutes();

        $this->post(route('login.mfa'), ['code' => '000000'])
            ->assertRedirect(route('login'))
            ->assertSessionMissing('auth.mfa.pending');
        $this->assertGuest();
        $this->travelBack();
    }

    public function test_mfa_challenge_can_be_cancelled_to_use_another_account(): void
    {
        config(['auth.mfa_enabled' => true]);
        $user = $this->mfaUser();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('login'));
        $this->delete(route('login.mfa.cancel'))
            ->assertRedirect(route('login'))
            ->assertSessionMissing('auth.mfa.pending');

        $this->get(route('login'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Auth/Login')
                ->where('mfaChallenge', false));
        $this->assertGuest();
    }

    public function test_invalid_credentials_return_a_validation_error(): void
    {
        $this->from('/login')->post('/login', [
            'email' => 'person@example.com',
            'password' => 'incorrect-password',
        ])->assertRedirect('/login')->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_guest_is_redirected_to_login_from_the_dashboard(): void
    {
        $this->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_the_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Dashboard')
                ->where('auth.user.email', $user->email));
    }

    public function test_authenticated_user_can_log_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->delete('/logout')
            ->assertRedirectToRoute('login');

        $this->assertGuest();
    }

    public function test_login_is_limited_to_five_attempts_per_email_and_ip_per_minute(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->from('/login')->post('/login', [
                'email' => 'person@example.com',
                'password' => 'incorrect-password',
            ])->assertRedirect('/login');
        }

        $this->post('/login', [
            'email' => 'person@example.com',
            'password' => 'incorrect-password',
        ])->assertTooManyRequests();
    }

    private function mfaUser(): User
    {
        $user = User::factory()->create();
        $user->createTwoFactorAuth();
        $this->assertTrue($user->confirmTwoFactorAuth($user->makeTwoFactorCode('now', -1)));

        return $user;
    }
}
