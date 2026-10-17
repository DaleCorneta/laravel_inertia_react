<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class SocialLoginConfigurationTest extends TestCase
{
    public function test_social_and_google_login_are_disabled_by_default(): void
    {
        $this->assertFalse(config('auth.social_login.enabled'));
        $this->assertFalse(config('auth.social_login.google_enabled'));
    }
}
