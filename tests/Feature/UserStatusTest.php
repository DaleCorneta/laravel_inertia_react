<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class UserStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_and_non_administrator_cannot_change_account_status(): void
    {
        $target = User::factory()->create();
        $this->patch(route('users.status', $target), ['is_active' => false])
            ->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())
            ->patch(route('users.status', $target), ['is_active' => false])
            ->assertForbidden();

        $this->assertTrue($target->fresh()->is_active);
    }

    public function test_administrator_can_deactivate_and_reactivate_a_regular_account(): void
    {
        $admin = User::factory()->create();
        $target = User::factory()->create();
        $this->makeAdministrator($admin);

        $this->actingAs($admin)
            ->get(route('users.show', $target))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('person.is_active', true)
                ->where('person.can_manage_status', true));

        $this->patch(route('users.status', $target), ['is_active' => false])
            ->assertRedirect(route('users.show', $target))
            ->assertSessionHas('status', 'User account deactivated.');
        $this->assertFalse($target->fresh()->is_active);

        $this->patch(route('users.status', $target), ['is_active' => true])
            ->assertRedirect(route('users.show', $target))
            ->assertSessionHas('status', 'User account activated.');
        $this->assertTrue($target->fresh()->is_active);

        $activities = Activity::query()->whereIn('event', ['user.deactivated', 'user.activated'])->orderBy('id')->get();
        $this->assertSame(['user.deactivated', 'user.activated'], $activities->pluck('event')->all());
        $this->assertSame([$admin->id, $admin->id], $activities->pluck('causer_id')->all());
        $this->assertSame([$target->id, $target->id], $activities->pluck('subject_id')->all());
    }

    public function test_administrator_cannot_deactivate_themselves_or_another_administrator(): void
    {
        $admin = User::factory()->create();
        $secondAdmin = User::factory()->create();
        $this->makeAdministrator($admin);
        $this->makeAdministrator($secondAdmin);

        $this->actingAs($admin)
            ->patch(route('users.status', $admin), ['is_active' => false])
            ->assertForbidden();

        $this->patch(route('users.status', $secondAdmin), ['is_active' => false])
            ->assertForbidden();

        $this->assertTrue($admin->fresh()->is_active);
        $this->assertTrue($secondAdmin->fresh()->is_active);
    }

    public function test_inactive_user_cannot_sign_in_or_continue_an_existing_session(): void
    {
        $user = User::factory()->create([
            'email' => 'inactive@example.test',
            'password' => Hash::make('ValidPassword123!'),
            'is_active' => false,
        ]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'ValidPassword123!',
        ])->assertSessionHasErrors('email');

        $this->actingAs($user);
        User::whereKey($user->id)->update(['is_active' => false]);

        $this->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Your account has been deactivated. Contact an administrator.');
    }

    public function test_status_requires_a_boolean_value(): void
    {
        $admin = User::factory()->create();
        $target = User::factory()->create();
        $this->makeAdministrator($admin);

        $this->actingAs($admin)
            ->from(route('users.show', $target))
            ->patch(route('users.status', $target), ['is_active' => 'maybe'])
            ->assertSessionHasErrors('is_active');

        $this->assertTrue($target->fresh()->is_active);
    }
}
