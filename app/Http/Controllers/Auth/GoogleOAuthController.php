<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LinkGoogleAccountRequest;
use App\Models\SocialAccount;
use App\Services\AuditLogger;
use App\Services\SocialLoginService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

class GoogleOAuthController extends Controller
{
    public function redirect(): SymfonyRedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request, SocialLoginService $socialLogin): RedirectResponse
    {
        try {
            $providerUser = Socialite::driver('google')->user();
        } catch (Throwable) {
            return $socialLogin->reject();
        }

        $subject = $providerUser->getId();

        if (! is_string($subject) || $subject === '') {
            return $socialLogin->reject();
        }

        return $socialLogin->signIn($request, 'google', $subject);
    }

    public function link(LinkGoogleAccountRequest $request): SymfonyRedirectResponse
    {
        $request->session()->put('auth.social.google.link', [
            'user_id' => $request->user()->id,
            'expires_at' => now()->addMinutes(10)->timestamp,
        ]);

        return Socialite::driver('google')->redirectUrl(url('/profile/social/google/callback'))->redirect();
    }

    public function linkCallback(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $link = $request->session()->pull('auth.social.google.link');

        try {
            $providerUser = Socialite::driver('google')->user();
        } catch (Throwable) {
            return $this->linkFailure();
        }

        if (! is_array($link)
            || ($link['user_id'] ?? null) !== $request->user()->id
            || ! is_int($link['expires_at'] ?? null)
            || $link['expires_at'] <= now()->timestamp) {
            return $this->linkFailure();
        }

        $subject = $providerUser->getId();

        if (! is_string($subject) || $subject === '') {
            return $this->linkFailure();
        }

        $existing = SocialAccount::query()->where('provider', 'google')->where('provider_user_id', $subject)->first();

        if ($existing && $existing->user_id !== $request->user()->id) {
            return $this->linkFailure();
        }

        if (! $existing) {
            try {
                DB::transaction(fn () => $request->user()->socialAccounts()->create([
                    'provider' => 'google',
                    'provider_user_id' => $subject,
                ]));
            } catch (QueryException) {
                return $this->linkFailure();
            }

            $auditLogger->log('auth.social_account_linked', $request->user());
        }

        return to_route('profile.show')->with('status', 'Google account linked successfully.');
    }

    private function linkFailure(): RedirectResponse
    {
        return to_route('profile.show')->with('status', 'Unable to link that Google account. Please try again.');
    }
}
