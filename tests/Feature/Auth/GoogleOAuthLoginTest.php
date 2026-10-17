<?php

namespace Tests\Feature\Auth;

use App\Http\Controllers\Auth\GoogleOAuthController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as ProviderUser;
use Tests\TestCase;

class GoogleOAuthLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'guest'])->get('/_test/google/redirect', [GoogleOAuthController::class, 'redirect']);
        Route::middleware(['web', 'guest'])->get('/_test/google/callback', [GoogleOAuthController::class, 'callback']);
    }

    public function test_redirect_uses_stateful_google_oauth(): void
    {
        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
            'services.google.redirect' => 'http://localhost/auth/google/callback',
        ]);

        $response = $this->get('/_test/google/redirect')->assertRedirect();
        parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);

        $this->assertNotEmpty($query['state'] ?? null);
        $this->assertSame('http://localhost/auth/google/callback', $query['redirect_uri'] ?? null);
        $this->assertSame($query['state'], session('state'));
    }

    public function test_linked_google_subject_signs_in_the_local_user(): void
    {
        $user = User::factory()->create(['email' => 'local@example.com']);
        $user->socialAccounts()->create(['provider' => 'google', 'provider_user_id' => 'google-subject']);
        Socialite::fake('google', ProviderUser::fake([
            'id' => 'google-subject',
            'email' => 'different-provider-email@example.com',
        ]));

        $this->get('/_test/google/callback')->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseCount('social_accounts', 1);
    }

    public function test_unlinked_google_subject_cannot_sign_in_by_matching_email(): void
    {
        User::factory()->create(['email' => 'person@example.com']);
        Socialite::fake('google', ProviderUser::fake([
            'id' => 'not-linked',
            'email' => 'person@example.com',
        ]));

        $this->get('/_test/google/callback')
            ->assertRedirect(route('login'))
            ->assertSessionHas('status');

        $this->assertGuest();
        $this->assertDatabaseCount('social_accounts', 0);
    }

    public function test_inactive_linked_user_cannot_sign_in(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        $user->socialAccounts()->create(['provider' => 'google', 'provider_user_id' => 'google-subject']);
        Socialite::fake('google', ProviderUser::fake(['id' => 'google-subject']));

        $this->get('/_test/google/callback')
            ->assertRedirect(route('login'))
            ->assertSessionHas('status');

        $this->assertGuest();
    }

    public function test_mfa_user_must_complete_the_existing_challenge_before_session_creation(): void
    {
        config(['auth.mfa_enabled' => true]);
        $user = User::factory()->create();
        $user->createTwoFactorAuth();
        $this->assertTrue($user->confirmTwoFactorAuth($user->makeTwoFactorCode('now', -1)));
        $user->socialAccounts()->create(['provider' => 'google', 'provider_user_id' => 'google-subject']);
        Socialite::fake('google', ProviderUser::fake(['id' => 'google-subject']));

        $this->get('/_test/google/callback')
            ->assertRedirect(route('login'))
            ->assertSessionHas('auth.mfa.pending.user_id', $user->id);
        $this->assertGuest();

        $this->post(route('login.mfa'), ['code' => $user->makeTwoFactorCode()])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_provider_state_failure_returns_a_generic_login_error(): void
    {
        Socialite::fake('google', fn () => throw new InvalidStateException);

        $this->get('/_test/google/callback')
            ->assertRedirect(route('login'))
            ->assertSessionHas('status');

        $this->assertGuest();
    }

    public function test_provider_response_without_a_subject_is_rejected(): void
    {
        Socialite::fake('google', ProviderUser::fake(['id' => null]));

        $this->get('/_test/google/callback')
            ->assertRedirect(route('login'))
            ->assertSessionHas('status');

        $this->assertGuest();
    }
}
