<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\MfaChallengeRequest;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\MfaLoginChallenge;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function create(Request $request): Response
    {
        $pending = $request->session()->get('auth.mfa.pending');
        $challenge = is_array($pending) && ($pending['expires_at'] ?? 0) > now()->timestamp;

        if (! $challenge && $pending !== null) {
            $request->session()->forget('auth.mfa.pending');
            $request->session()->flash('status', 'Your sign-in challenge expired. Please sign in again.');
        }

        return Inertia::render('Auth/Login', [
            'mfaChallenge' => $challenge,
            'googleLoginEnabled' => ! $challenge && config('auth.social_login.enabled') && config('auth.social_login.google_enabled'),
        ]);
    }

    public function store(LoginRequest $request, AuditLogger $auditLogger, MfaLoginChallenge $mfaLoginChallenge): RedirectResponse
    {
        $credentials = $request->safe()->only(['email', 'password']);
        $credentials['is_active'] = true;

        $challengeUser = null;
        $authenticated = Auth::guard('web')->attemptWhen(
            $credentials,
            function ($user) use (&$challengeUser, $credentials): bool {
                if (config('auth.mfa_enabled') && $user instanceof User && $user->hasTwoFactorEnabled()) {
                    Auth::guard('web')->getProvider()->rehashPasswordIfRequired($user, $credentials);
                    $challengeUser = $user;

                    return false;
                }

                return true;
            },
            $request->boolean('remember'),
        );

        if ($challengeUser instanceof User) {
            $mfaLoginChallenge->start($request, $challengeUser, $request->boolean('remember'));

            return to_route('login');
        }

        if (! $authenticated) {
            $auditLogger->log('auth.login_failed');

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        $request->session()->regenerate();

        $auditLogger->log('auth.login_succeeded', $request->user());

        return redirect()->intended(route('dashboard'));
    }

    public function challenge(MfaChallengeRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $pending = $request->session()->get('auth.mfa.pending');

        if (! is_array($pending) || ($pending['expires_at'] ?? 0) <= now()->timestamp) {
            $request->session()->forget('auth.mfa.pending');

            return to_route('login')->with('status', 'Your sign-in challenge expired. Please sign in again.');
        }

        $user = User::query()->find($pending['user_id'] ?? null);

        if (! config('auth.mfa_enabled') || ! $user || ! $user->is_active || ! $user->hasTwoFactorEnabled()) {
            $request->session()->forget('auth.mfa.pending');

            return to_route('login')->with('status', 'Please sign in again to continue.');
        }

        if (! $user->validateTwoFactorCode($request->validated('code'))) {
            $auditLogger->log('auth.login_failed');

            throw ValidationException::withMessages(['code' => 'The authenticator or recovery code is invalid.']);
        }

        $request->session()->forget('auth.mfa.pending');
        Auth::guard('web')->login($user, (bool) ($pending['remember'] ?? false));
        $request->session()->regenerate();

        $auditLogger->log('auth.login_succeeded', $user);

        return redirect()->intended(route('dashboard'));
    }

    public function cancelChallenge(Request $request): RedirectResponse
    {
        $request->session()->forget('auth.mfa.pending');

        return to_route('login');
    }

    public function destroy(AuditLogger $auditLogger): RedirectResponse
    {
        $auditLogger->log('auth.logout', request()->user());
        Auth::guard('web')->logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    }
}
