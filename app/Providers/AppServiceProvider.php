<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('users.deactivate', function (User $actor, User $target): bool {
            return $actor->id !== $target->id
                && ! $target->hasRole('administrator')
                && $actor->can('users.update');
        });

        RateLimiter::for('login', function (Request $request) {
            $identity = $request->input('email')
                ?: 'mfa:'.$request->session()->get('auth.mfa.pending.user_id', '');
            $email = Str::limit(Str::lower((string) $identity), 255, '');

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });

        RateLimiter::for('password-reset', function (Request $request) {
            $email = Str::limit(Str::lower((string) $request->input('email')), 255, '');

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });

        RateLimiter::for('verification', function (Request $request) {
            $email = Str::limit(Str::lower((string) $request->user()?->getEmailForVerification()), 255, '');

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });

        RateLimiter::for('mfa-management', fn (Request $request) => Limit::perMinute(10)->by($request->user()->getAuthIdentifier().'|'.$request->ip())
        );
    }
}
