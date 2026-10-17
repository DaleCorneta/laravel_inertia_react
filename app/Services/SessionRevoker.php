<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class SessionRevoker
{
    public function revokeOtherSessions(User $user, ?string $exceptSessionId = null): void
    {
        // shortcut: revocation targets database sessions, extend if SESSION_DRIVER changes.
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::connection(config('session.connection'))
            ->table(config('session.table', 'sessions'))
            ->where('user_id', $user->getAuthIdentifier())
            ->when($exceptSessionId !== null, fn ($query) => $query->where('id', '!=', $exceptSessionId))
            ->delete();
    }
}
