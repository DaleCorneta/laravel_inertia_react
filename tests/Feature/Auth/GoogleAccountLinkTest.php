<?php

namespace Tests\Feature\Auth;

use App\Http\Controllers\Auth\GoogleOAuthController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as ProviderUser;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class GoogleAccountLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth'])->post('/_test/google/link', [GoogleOAuthController::class, 'link']);
        Route::middleware(['web', 'auth'])->get('/_test/google/link/callback', [GoogleOAuthController::class, 'linkCallback']);
    }

    public function test_linking_requires_the_current_password_before_redirecting(): void
    {
        $user = User::factory()->create(['password' => 'correct-password']);
        $this->actingAs($user);

        $this->post('/_test/google/link', ['current_password' => 'incorrect-password'])
            ->assertSessionHasErrors('current_password')
            ->assertSessionMissing('auth.social.google.link');

        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
            'services.google.redirect' => 'http://localhost/profile/social/google/callback',
        ]);
        $response = $this->post('/_test/google/link', ['current_password' => 'correct-password'])->assertRedirect();
        parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->assertSame(url('/profile/social/google/callback'), $query['redirect_uri'] ?? null);
        $this->assertSame($user->id, session('auth.social.google.link.user_id'));
    }

    public function test_callback_links_the_google_subject_without_using_provider_email_or_storing_tokens(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->withSession([
            'auth.social.google.link' => ['user_id' => $user->id, 'expires_at' => now()->addMinutes(5)->timestamp],
        ]);
        Socialite::fake('google', ProviderUser::fake([
            'id' => 'google-subject',
            'email' => 'somebody-else@example.test',
            'token' => 'sensitive-provider-token',
        ]));

        $this->get('/_test/google/link/callback')
            ->assertRedirect(route('profile.show'))
            ->assertSessionHas('status', 'Google account linked successfully.')
            ->assertSessionMissing('auth.social.google.link');

        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'google-subject',
        ]);
        $activity = Activity::query()->where('event', 'auth.social_account_linked')->sole();
        $this->assertSame($user->id, $activity->subject_id);
        $this->assertSame([], $activity->properties->toArray());
        $this->assertStringNotContainsString('somebody-else@example.test', $activity->toJson());
        $this->assertStringNotContainsString('sensitive-provider-token', $activity->toJson());
    }

    public function test_callback_rejects_mismatched_link_intent(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $this->actingAs($user)->withSession([
            'auth.social.google.link' => ['user_id' => $otherUser->id, 'expires_at' => now()->addMinutes(5)->timestamp],
        ]);
        Socialite::fake('google', ProviderUser::fake(['id' => 'google-subject']));

        $this->get('/_test/google/link/callback')
            ->assertRedirect(route('profile.show'))
            ->assertSessionMissing('auth.social.google.link');
        $this->assertDatabaseCount('social_accounts', 0);
    }

    public function test_callback_rejects_expired_link_intent(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->withSession([
            'auth.social.google.link' => ['user_id' => $user->id, 'expires_at' => now()->subSecond()->timestamp],
        ]);
        Socialite::fake('google', ProviderUser::fake(['id' => 'google-subject']));

        $this->get('/_test/google/link/callback')->assertRedirect(route('profile.show'));
        $this->assertDatabaseCount('social_accounts', 0);
    }

    public function test_callback_cannot_move_a_google_identity_from_another_user(): void
    {
        $user = User::factory()->create();
        $owner = User::factory()->create();
        $owner->socialAccounts()->create(['provider' => 'google', 'provider_user_id' => 'owned-subject']);
        $this->actingAs($user)->withSession([
            'auth.social.google.link' => ['user_id' => $user->id, 'expires_at' => now()->addMinutes(5)->timestamp],
        ]);
        Socialite::fake('google', ProviderUser::fake(['id' => 'owned-subject']));

        $this->get('/_test/google/link/callback')
            ->assertRedirect(route('profile.show'))
            ->assertSessionHas('status', 'Unable to link that Google account. Please try again.');

        $this->assertDatabaseCount('social_accounts', 1);
        $this->assertDatabaseHas('social_accounts', ['user_id' => $owner->id, 'provider_user_id' => 'owned-subject']);
        $this->assertDatabaseMissing('activity_log', ['event' => 'auth.social_account_linked']);
    }

    public function test_provider_failure_clears_link_intent_without_exposing_error_details(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->withSession([
            'auth.social.google.link' => ['user_id' => $user->id, 'expires_at' => now()->addMinutes(5)->timestamp],
        ]);
        Socialite::fake('google', fn () => throw new InvalidStateException);

        $this->get('/_test/google/link/callback')
            ->assertRedirect(route('profile.show'))
            ->assertSessionHas('status', 'Unable to link that Google account. Please try again.')
            ->assertSessionMissing('auth.social.google.link');

        $this->assertDatabaseCount('social_accounts', 0);
    }
}
