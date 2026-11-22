<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        // Broad per-address cap on the sign-in route; LoginThrottle adds the per-account cap.
        RateLimiter::for('login', fn (Request $request) => \App\Support\TrustedDevices::trusted($request) ? Limit::none() : Limit::perMinute(\App\Support\SecurityLimits::get('login_per_minute'))->by($request->ip()));

        // The public application form: a few per hour for each address (config/orphan_fields.php).
        RateLimiter::for('apply', fn (Request $request) => Limit::perHour((int) config('orphan_fields.apply.per_hour', 6))->by($request->ip()));

        RateLimiter::for('api', function (Request $request) {
            // The staff app saves pages on the phone for offline use, so a signed-in account gets a generous allowance.
            if (\App\Support\TrustedDevices::trusted($request)) {
                return Limit::none();   // a device the person chose to trust is never slowed down
            }

            return $request->user()
                ? Limit::perMinute(\App\Support\SecurityLimits::get('api_per_minute_user'))->by('u'.$request->user()->id)
                : Limit::perMinute(\App\Support\SecurityLimits::get('api_per_minute_ip'))->by($request->ip());
        });

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::group([], base_path('routes/install.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
