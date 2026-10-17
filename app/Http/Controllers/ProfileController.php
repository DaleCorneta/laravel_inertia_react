<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Services\AuditLogger;
use App\Services\SessionRevoker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Profile/Show', [
            'profile' => [
                'prefix' => $user->prefix,
                'first_name' => $user->first_name,
                'middle_name' => $user->middle_name,
                'last_name' => $user->last_name,
                'suffix' => $user->suffix,
                'email' => $user->email,
                'email_verified' => $user->hasVerifiedEmail(),
                'mfa_enabled' => config('auth.mfa_enabled'),
                'google_login_enabled' => config('auth.social_login.enabled') && config('auth.social_login.google_enabled'),
                'google_account_linked' => $user->socialAccounts()->where('provider', 'google')->exists(),
            ],
        ]);
    }

    public function update(UpdateProfileRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $data = $request->safe()->except('current_password');
        $user = $request->user();
        $emailChanged = mb_strtolower($user->email) !== $data['email'];

        $user->fill($data);

        if ($emailChanged) {
            $user->forceFill(['email_verified_at' => null]);
        }

        $user->save();

        $auditLogger->log(
            $emailChanged ? 'profile.email_changed' : 'profile.updated',
            $user,
            ['fields' => array_keys($user->getChanges())],
        );

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();

            return to_route('verification.notice')
                ->with('status', 'Profile updated. Verify your new email address to continue.');
        }

        return to_route('profile.show')->with('status', 'Profile updated.');
    }

    public function updatePassword(ChangePasswordRequest $request, AuditLogger $auditLogger, SessionRevoker $sessionRevoker): RedirectResponse
    {
        $user = $request->user();
        $user->forceFill([
            'password' => $request->validated('password'),
            'remember_token' => Str::random(60),
        ])->save();

        $sessionRevoker->revokeOtherSessions($user, $request->session()->getId());
        $auditLogger->log('profile.password_changed', $user);

        $request->session()->regenerate();

        return to_route('profile.show')->with('status', 'Password updated.');
    }
}
