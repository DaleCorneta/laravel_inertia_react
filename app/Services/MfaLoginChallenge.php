<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;

class MfaLoginChallenge
{
    public function start(Request $request, User $user, bool $remember = false): void
    {
        $request->session()->put('auth.mfa.pending', [
            'user_id' => $user->getAuthIdentifier(),
            'remember' => $remember,
            'expires_at' => now()->addMinutes(5)->timestamp,
        ]);
        $request->session()->regenerate();
    }
}
