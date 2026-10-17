<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_from_the_verification_notice(): void
    {
        $this->get(route('verification.notice'))
            ->assertRedirect(route('login'));
    }

    public function test_unverified_user_sees_the_notice_and_cannot_open_the_dashboard(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get(route('verification.notice'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Auth/VerifyEmail')
                ->where('auth.user.email', $user->email));

        $this->get(route('dashboard'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_unverified_user_can_request_a_verification_notification(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->from(route('verification.notice'))
            ->post(route('verification.send'))
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('status', 'A new verification link has been sent to your email address.');

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_valid_signed_link_marks_email_verified_and_dispatches_event(): void
    {
        Event::fake([Verified::class]);
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())],
        );

        $this->actingAs($user)
            ->get($url)
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('status', 'Your email address has been verified.');

        $this->assertNotNull($user->fresh()->email_verified_at);
        Event::assertDispatched(Verified::class, fn (Verified $event) => $event->user->is($user));
    }

    public function test_expired_signed_link_is_rejected_without_verifying_email(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->subMinute(),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())],
        );

        $this->actingAs($user)->get($url)->assertForbidden();

        $this->assertNull($user->fresh()->email_verified_at);
    }
}
