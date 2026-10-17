<?php

namespace App\Http\Controllers;

use App\Http\Requests\BeginMfaEnrollmentRequest;
use App\Http\Requests\ConfirmMfaEnrollmentRequest;
use App\Http\Requests\VerifyMfaActionRequest;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProfileMfaController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();
        $setup = ! $user->hasTwoFactorEnabled() && $user->twoFactorAuth->exists
            ? [
                'qr_code' => base64_encode($user->twoFactorAuth->toQr()),
                'secret' => $user->twoFactorAuth->toString(),
            ]
            : null;

        return Inertia::render('Profile/Mfa', [
            'enabled' => $user->hasTwoFactorEnabled(),
            'setup' => $setup,
            'recoveryCodes' => $request->session()->pull('mfa.recovery_codes', []),
        ]);
    }

    public function begin(BeginMfaEnrollmentRequest $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasTwoFactorEnabled()) {
            throw ValidationException::withMessages(['current_password' => 'Two-factor authentication is already enabled.']);
        }

        $user->createTwoFactorAuth();

        return to_route('profile.mfa.show');
    }

    public function confirm(ConfirmMfaEnrollmentRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasTwoFactorEnabled() || ! $user->twoFactorAuth->exists) {
            throw ValidationException::withMessages(['code' => 'Start a new authenticator setup before confirming it.']);
        }

        if (! $user->confirmTwoFactorAuth($request->validated('code'))) {
            throw ValidationException::withMessages(['code' => 'The authenticator code is invalid.']);
        }

        $request->session()->flash('mfa.recovery_codes', $user->getRecoveryCodes()->pluck('code')->all());
        $auditLogger->log('mfa.enabled', $user);

        return to_route('profile.mfa.show')->with('status', 'Two-factor authentication enabled. Save your recovery codes now.');
    }

    public function disable(VerifyMfaActionRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $user = $request->user();
        $this->validateCurrentCode($user, $request->validated('code'));

        $user->disableTwoFactorAuth();
        $auditLogger->log('mfa.disabled', $user);

        return to_route('profile.mfa.show')->with('status', 'Two-factor authentication disabled.');
    }

    public function regenerateRecoveryCodes(VerifyMfaActionRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $user = $request->user();
        $this->validateCurrentCode($user, $request->validated('code'));

        $request->session()->flash('mfa.recovery_codes', $user->generateRecoveryCodes()->pluck('code')->all());
        $auditLogger->log('mfa.recovery_codes_regenerated', $user);

        return to_route('profile.mfa.show')->with('status', 'Recovery codes regenerated. Save the new codes now.');
    }

    private function validateCurrentCode(User $user, string $code): void
    {
        if (! $user->hasTwoFactorEnabled() || ! $user->validateTwoFactorCode($code)) {
            throw ValidationException::withMessages(['code' => 'The authenticator or recovery code is invalid.']);
        }
    }
}
