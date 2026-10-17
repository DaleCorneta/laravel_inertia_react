<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ProfileManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_or_update_profile_or_password(): void
    {
        $this->get(route('profile.show'))->assertRedirect(route('login'));
        $this->put(route('profile.update'), [])->assertRedirect(route('login'));
        $this->put(route('profile.password.update'), [])->assertRedirect(route('login'));
    }

    public function test_user_can_view_and_update_their_profile_without_reverifying_an_unchanged_email(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'first_name' => 'Jordan',
            'last_name' => 'Rivera',
            'email' => 'jordan@example.test',
        ]);

        $this->actingAs($user)
            ->get(route('profile.show'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Profile/Show')
                ->where('profile.first_name', 'Jordan')
                ->where('profile.email_verified', true)
                ->missing('profile.password'));

        $this->put(route('profile.update'), [
            'prefix' => 'Dr.',
            'first_name' => 'Jordan-Ann',
            'middle_name' => 'Morgan',
            'last_name' => 'Rivera',
            'suffix' => '',
            'email' => ' JORDAN@EXAMPLE.TEST ',
            'current_password' => 'password',
        ])->assertRedirect(route('profile.show'))
            ->assertSessionHas('status', 'Profile updated.');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'first_name' => 'Jordan-Ann',
            'middle_name' => 'Morgan',
            'email' => 'jordan@example.test',
        ]);
        $this->assertNotNull($user->fresh()->email_verified_at);
        Notification::assertNothingSent();
    }

    public function test_profile_email_change_requires_current_password_and_reverifies_new_address(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'before@example.test']);
        $profile = [
            'first_name' => 'Taylor',
            'last_name' => 'Morgan',
            'email' => 'NEW.ADDRESS@EXAMPLE.TEST',
        ];

        $this->actingAs($user)
            ->from(route('profile.show'))
            ->put(route('profile.update'), [...$profile, 'current_password' => 'incorrect-password'])
            ->assertSessionHasErrors('current_password');

        $this->assertSame('before@example.test', $user->fresh()->email);

        $this->put(route('profile.update'), [...$profile, 'current_password' => 'password'])
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('status', 'Profile updated. Verify your new email address to continue.');

        $updated = $user->fresh();
        $this->assertSame('new.address@example.test', $updated->email);
        $this->assertNull($updated->email_verified_at);
        Notification::assertSentTo($updated, VerifyEmail::class);

        $activity = Activity::query()->where('event', 'profile.email_changed')->sole();
        $this->assertSame($user->id, $activity->causer_id);
        $this->assertSame($user->id, $activity->subject_id);
        $this->assertStringNotContainsString('new.address@example.test', $activity->toJson());
        $this->assertStringNotContainsString('Taylor', $activity->toJson());
    }

    public function test_password_change_requires_current_password_and_a_valid_confirmed_new_password(): void
    {
        $user = User::factory()->create(['remember_token' => 'existing-remember-token']);
        config(['session.driver' => 'database']);
        DB::table('sessions')->insert([
            'id' => 'other-session',
            'user_id' => $user->id,
            'payload' => base64_encode(serialize([])),
            'last_activity' => time(),
        ]);
        $this->actingAs($user);

        $this->from(route('profile.show'))
            ->put(route('profile.password.update'), [
                'current_password' => 'incorrect-password',
                'password' => 'NewStrongPass123!',
                'password_confirmation' => 'NewStrongPass123!',
            ])
            ->assertSessionHasErrors('current_password');

        $this->put(route('profile.password.update'), [
            'current_password' => 'password',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])->assertSessionHasErrors(['password']);

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
        $this->assertSame('existing-remember-token', $user->fresh()->remember_token);

        $this->put(route('profile.password.update'), [
            'current_password' => 'password',
            'password' => 'NewStrongPass123!',
            'password_confirmation' => 'NewStrongPass123!',
        ])->assertRedirect(route('profile.show'))
            ->assertSessionHas('status', 'Password updated.');

        $updated = $user->fresh();
        $this->assertTrue(Hash::check('NewStrongPass123!', $updated->password));
        $this->assertNotSame('NewStrongPass123!', $updated->password);
        $this->assertNotSame('existing-remember-token', $updated->remember_token);
        $this->assertDatabaseMissing('sessions', ['id' => 'other-session']);
        $this->get(route('profile.show'))->assertOk();

        $activity = Activity::query()->where('event', 'profile.password_changed')->sole();
        $this->assertStringNotContainsString('NewStrongPass123!', $activity->toJson());
        $this->assertStringNotContainsString($updated->password, $activity->toJson());
        $this->assertStringNotContainsString($updated->remember_token, $activity->toJson());
    }
}
