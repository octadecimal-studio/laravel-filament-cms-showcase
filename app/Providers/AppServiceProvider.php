<?php

namespace App\Providers;

use App\Modules\Content\Models\ContentBlock;
use App\Observers\ContentBlockObserver;
use App\Modules\Content\Models\SiteContent;
use App\Observers\SiteContentObserver;
use App\Modules\Content\Models\TwoWheels\SiteSetting;
use App\Modules\Content\Models\TwoWheels\Feature;
use App\Modules\Content\Models\TwoWheels\Motorcycle;
use App\Modules\Content\Models\TwoWheels\MotorcycleBrand;
use App\Modules\Content\Models\TwoWheels\MotorcycleCategory;
use App\Modules\Content\Models\TwoWheels\Testimonial;
use App\Modules\Content\Models\TwoWheels\ProcessStep;
use App\Modules\Content\Models\TwoWheels\RentalCondition;
use App\Modules\Content\Models\TwoWheels\PricingNote;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use App\Plugins\Reservations\Models\Reservation;
use App\Policies\ReservationPolicy;
use App\Session\DatabaseSessionHandler;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
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
        if (class_exists(ContentBlock::class)) {
            ContentBlock::observe(ContentBlockObserver::class);
        }

        if (class_exists(SiteContent::class)) {
            SiteContent::observe(SiteContentObserver::class);
        }

        // Clear application cache when any MotoRent content model is saved or deleted
        $twoWheelsModels = [
            SiteSetting::class,
            Feature::class,
            Motorcycle::class,
            MotorcycleBrand::class,
            MotorcycleCategory::class,
            Testimonial::class,
            ProcessStep::class,
            RentalCondition::class,
            PricingNote::class,
        ];

        $clearCache = function () {
            Artisan::call('cache:clear');
        };

        foreach ($twoWheelsModels as $model) {
            if (class_exists($model)) {
                $model::saved($clearCache);
                $model::deleted($clearCache);
            }
        }

        // Configure rate limiting
        RateLimiter::for('api', function ($request) {
            return Limit::perMinute(300)->by($request->ip());
        });
    }
}
