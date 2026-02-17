<?php

namespace App\Providers;

use App\Plugins\Reservations\Models\Reservation;
use App\Policies\ReservationPolicy;
use App\Session\DatabaseSessionHandler;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\URL;
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
        // === POLICY MAPPINGS ===
        Gate::policy(Reservation::class, ReservationPolicy::class);

        // Wymuszenie APP_URL dla generowania URL-i
        $appUrl = config('app.url');
        if ($appUrl) {
            URL::forceRootUrl($appUrl);
        }

        // Rejestruj custom DatabaseSessionHandler dla sesji w bazie danych
        if (config('session.driver') === 'database' && config('session.use_custom_handler', false)) {
            Session::extend('database', function (Application $app) {
                $connection = $app['db']->connection(config('session.connection'));
                $table = config('session.table', 'sessions');
                $lifetime = config('session.lifetime', 120);

                return new DatabaseSessionHandler($connection, $table, $lifetime, $app);
            });
        }

        // Register observers for content revalidation
        if (class_exists(\App\Modules\Content\Models\ContentBlock::class)) {
            \App\Modules\Content\Models\ContentBlock::observe(\App\Observers\ContentBlockObserver::class);
        }

        if (class_exists(\App\Modules\Content\Models\SiteContent::class)) {
            \App\Modules\Content\Models\SiteContent::observe(\App\Observers\SiteContentObserver::class);
        }

        // Configure rate limiting
        \Illuminate\Support\Facades\RateLimiter::for('api', function ($request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(300)->by($request->ip());
        });
    }
}
