<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ProfileMfaTest extends TestCase
{
    use RefreshDatabase;

    public function test_mfa_is_disabled_by_default_and_management_routes_return_not_found(): void
    {
        $user = $this->verifiedUser();
        $this->assertFalse(config('auth.mfa_enabled'));

        $this->actingAs($user)
            ->get(route('profile.show'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('profile.mfa_enabled', false));

        $this->get(route('profile.mfa.show'))->assertNotFound();
        $this->post(route('profile.mfa.begin'), ['current_password' => 'password'])->assertNotFound();
    }

    public function test_guest_cannot_access_mfa_management_when_enabled(): void
    {
        config(['auth.mfa_enabled' => true]);

        $this->get(route('profile.mfa.show'))->assertRedirect(route('login'));
    }

    public function test_user_can_enroll_confirm_and_view_recovery_codes_once(): void
    {
        config(['auth.mfa_enabled' => true]);
        $user = $this->verifiedUser();
        $this->actingAs($user);

        $this->post(route('profile.mfa.begin'), ['current_password' => 'wrong-password'])
            ->assertSessionHasErrors('current_password');

        $this->post(route('profile.mfa.begin'), ['current_password' => 'password'])
            ->assertRedirect(route('profile.mfa.show'));

        $pendingUser = $user->fresh();
        $secret = $pendingUser->twoFactorAuth->toString();
        $storedSecret = DB::table('two_factor_authentications')->where('authenticatable_id', $user->id)->value('shared_secret');
        $this->assertNotSame($secret, $storedSecret);

        $this->get(route('profile.mfa.show'))->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('enabled', false)
                ->where('setup.secret', $secret)
                ->where('setup.qr_code', base64_encode($pendingUser->twoFactorAuth->toQr())));

        $expiredCode = $pendingUser->makeTwoFactorCode(now()->subHour());
        $this->post(route('profile.mfa.confirm'), [
            'current_password' => 'password',
            'code' => $expiredCode,
        ])->assertSessionHasErrors('code');

        $this->post(route('profile.mfa.confirm'), [
            'current_password' => 'password',
            'code' => $pendingUser->makeTwoFactorCode(),
        ])->assertRedirect(route('profile.mfa.show'));

        $recoveryResponse = $this->get(route('profile.mfa.show'));
        $pageData = $recoveryResponse->viewData('page');
        $this->assertSame('Profile/Mfa', $pageData['component']);
        $this->assertTrue($pageData['props']['enabled']);
        $this->assertNull($pageData['props']['setup']);
        $this->assertCount(10, $pageData['props']['recoveryCodes']);

        $storedCodes = DB::table('two_factor_authentications')->where('authenticatable_id', $user->id)->value('recovery_codes');
        $this->assertNotSame('', $storedCodes);
        $this->assertStringNotContainsString($user->fresh()->getRecoveryCodes()->first()['code'], $storedCodes);
        $this->assertSame([], $this->get(route('profile.mfa.show'))->viewData('page')['props']['recoveryCodes']);

        $activity = Activity::query()->where('event', 'mfa.enabled')->sole();
        $this->assertStringNotContainsString($secret, $activity->toJson());
        $this->assertStringNotContainsString($storedCodes, $activity->toJson());
    }

    public function test_disabling_mfa_requires_password_and_current_or_unused_recovery_code(): void
    {
        config(['auth.mfa_enabled' => true]);
        $user = $this->enabledUser();
        $recoveryCode = $user->fresh()->getRecoveryCodes()->first()['code'];
        $this->actingAs($user);

        $this->delete(route('profile.mfa.disable'), [
            'current_password' => 'wrong-password',
            'code' => $recoveryCode,
        ])->assertSessionHasErrors('current_password');
        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());

        $this->delete(route('profile.mfa.disable'), [
            'current_password' => 'password',
            'code' => $user->makeTwoFactorCode(now()->subHour()),
        ])->assertSessionHasErrors('code');
        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());

        $this->delete(route('profile.mfa.disable'), [
            'current_password' => 'password',
            'code' => $recoveryCode,
        ])->assertRedirect(route('profile.mfa.show'));

        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
        $this->assertDatabaseMissing('two_factor_authentications', ['authenticatable_id' => $user->id]);
        $this->assertDatabaseHas('activity_log', ['event' => 'mfa.disabled']);
    }

    public function test_regenerating_recovery_codes_invalidates_the_previous_batch(): void
    {
        config(['auth.mfa_enabled' => true]);
        $user = $this->enabledUser();
        $oldCode = $user->fresh()->getRecoveryCodes()->first()['code'];
        $this->actingAs($user);

        $this->post(route('profile.mfa.recovery-codes.regenerate'), [
            'current_password' => 'password',
            'code' => $user->makeTwoFactorCode('now', -1),
        ])->assertRedirect(route('profile.mfa.show'));

        $this->assertCount(10, $this->get(route('profile.mfa.show'))->viewData('page')['props']['recoveryCodes']);

        $this->assertFalse($user->fresh()->validateTwoFactorCode($oldCode));
        $this->assertDatabaseHas('activity_log', ['event' => 'mfa.recovery_codes_regenerated']);
    }

    private function verifiedUser(): User
    {
        return User::factory()->create([
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);
    }

    private function enabledUser(): User
    {
        $user = $this->verifiedUser();
        $user->createTwoFactorAuth();
        $this->assertTrue($user->confirmTwoFactorAuth($user->makeTwoFactorCode()));

        return $user;
    }
}
