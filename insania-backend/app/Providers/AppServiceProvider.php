<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

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
        // A sign-in that has been idle for too long is ended (stricter for the roles that can change access or see everything).
        \Laravel\Sanctum\Sanctum::authenticateAccessTokensUsing(function ($token, bool $isValid) {
            if (! $isValid || ! ($user = $token->tokenable)) {
                return false;
            }
            $key = in_array($user->role, (array) config('security.privileged_roles'), true) ? 'privileged' : 'default';
            $last = $token->last_used_at ?? $token->created_at;
            if ($last && $last->lt(now()->subMinutes((int) config("security.idle_minutes.$key", 240)))) {
                $token->delete();

                return false;
            }

            return true;
        });

        // Once installed with an https address, every link the server makes uses https too.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }
    }
}
