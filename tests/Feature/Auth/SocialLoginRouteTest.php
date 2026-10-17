<?php

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class SocialLoginRouteTest extends TestCase
{
    public function test_google_oauth_routes_are_registered_only_when_both_flags_are_enabled(): void
    {
        $keys = ['SOCIAL_LOGIN_ENABLED', 'GOOGLE_LOGIN_ENABLED'];
        $original = [];

        foreach ($keys as $key) {
            $original[$key] = [
                'env' => $_ENV[$key] ?? null,
                'server' => $_SERVER[$key] ?? null,
                'process' => getenv($key),
            ];
        }

        try {
            foreach ([[false, false], [true, false], [false, true], [true, true]] as [$socialEnabled, $googleEnabled]) {
                $_ENV['SOCIAL_LOGIN_ENABLED'] = $_SERVER['SOCIAL_LOGIN_ENABLED'] = $socialEnabled ? 'true' : 'false';
                $_ENV['GOOGLE_LOGIN_ENABLED'] = $_SERVER['GOOGLE_LOGIN_ENABLED'] = $googleEnabled ? 'true' : 'false';
                putenv('SOCIAL_LOGIN_ENABLED='.$_ENV['SOCIAL_LOGIN_ENABLED']);
                putenv('GOOGLE_LOGIN_ENABLED='.$_ENV['GOOGLE_LOGIN_ENABLED']);

                $this->refreshApplication();

                $expected = $socialEnabled && $googleEnabled;
                $this->get('/login')->assertInertia(fn (AssertableInertia $page) => $page
                    ->component('Auth/Login')
                    ->where('googleLoginEnabled', $expected));
                $this->assertSame($expected, Route::has('oauth.google.redirect'));
                $this->assertSame($expected, Route::has('oauth.google.callback'));
                $this->assertSame($expected, Route::has('profile.social.google.link'));
                $this->assertSame($expected, Route::has('profile.social.google.callback'));

                if ($expected) {
                    $linkMiddleware = Route::getRoutes()->getByName('profile.social.google.link')->gatherMiddleware();
                    $callbackMiddleware = Route::getRoutes()->getByName('profile.social.google.callback')->gatherMiddleware();
                    $this->assertContains('auth', $linkMiddleware);
                    $this->assertContains('verified', $linkMiddleware);
                    $this->assertContains('auth', $callbackMiddleware);
                    $this->assertContains('verified', $callbackMiddleware);
                }

                if (! $expected) {
                    $this->get('/auth/google/redirect')->assertNotFound();
                    $this->get('/auth/google/callback')->assertNotFound();
                    $this->post('/profile/social/google/link')->assertNotFound();
                    $this->get('/profile/social/google/callback')->assertNotFound();
                }
            }
        } finally {
            foreach ($original as $key => $values) {
                foreach (['env' => '_ENV', 'server' => '_SERVER'] as $source => $target) {
                    if ($values[$source] === null) {
                        unset($GLOBALS[$target][$key]);
                    } else {
                        $GLOBALS[$target][$key] = $values[$source];
                    }
                }

                if ($values['process'] === false) {
                    putenv($key);
                } else {
                    putenv($key.'='.$values['process']);
                }
            }

            $this->refreshApplication();
        }
    }
}
