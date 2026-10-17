<?php

namespace Tests\Feature\Auth;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_social_account_is_related_to_its_local_user(): void
    {
        $user = User::factory()->create();
        $account = $user->socialAccounts()->create([
            'provider' => 'google',
            'provider_user_id' => 'google-subject-123',
        ]);

        $this->assertInstanceOf(SocialAccount::class, $account);
        $this->assertSame($user->id, $account->user->id);
        $this->assertSame($account->id, $user->fresh()->socialAccounts->sole()->id);
    }

    public function test_provider_identity_must_be_unique_but_is_case_sensitive(): void
    {
        $user = User::factory()->create();
        $user->socialAccounts()->create([
            'provider' => 'google',
            'provider_user_id' => 'subject-A',
        ]);

        $user->socialAccounts()->create([
            'provider' => 'google',
            'provider_user_id' => 'subject-a',
        ]);

        $this->expectException(QueryException::class);
        $user->socialAccounts()->create([
            'provider' => 'google',
            'provider_user_id' => 'subject-A',
        ]);
    }
}
