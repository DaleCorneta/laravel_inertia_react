<?php

namespace App\Services;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SocialLoginService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly MfaLoginChallenge $mfaLoginChallenge,
    ) {}

    public function signIn(Request $request, string $provider, string $subject): RedirectResponse
    {
        $user = SocialAccount::query()
            ->where('provider', $provider)
            ->where('provider_user_id', $subject)
            ->first()
            ?->user;

        if (! $user instanceof User || ! $user->is_active) {
            return $this->reject();
        }

        if (config('auth.mfa_enabled') && $user->hasTwoFactorEnabled()) {
            $this->mfaLoginChallenge->start($request, $user);

            return to_route('login');
        }

        $request->session()->forget('auth.mfa.pending');
        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        $this->auditLogger->log('auth.login_succeeded', $user);

        return redirect()->intended(route('dashboard'));
    }

    public function reject(): RedirectResponse
    {
        $this->auditLogger->log('auth.login_failed');

        return to_route('login')->with('status', 'Google sign-in could not be completed. Please use your assigned account or contact an administrator.');
    }
}
