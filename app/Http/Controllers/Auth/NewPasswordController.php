<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\SessionRevoker;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class NewPasswordController extends Controller
{
    public function create(Request $request, string $token): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'email' => $request->query('email', ''),
            'token' => $token,
        ]);
    }

    public function store(ResetPasswordRequest $request, AuditLogger $auditLogger, SessionRevoker $sessionRevoker): RedirectResponse
    {
        $status = Password::reset($request->validated(), function (User $user, string $password) use ($auditLogger, $sessionRevoker): void {
            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ])->save();

            $sessionRevoker->revokeOtherSessions($user);
            event(new PasswordReset($user));
            $auditLogger->log('auth.password_reset', $user);
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [trans($status)],
            ]);
        }

        return redirect()->route('login')->with('status', 'Your password has been reset. You can now sign in.');
    }
}
