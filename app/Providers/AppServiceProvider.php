<?php

namespace App\Providers;

use App\Events\DeploymentCompleted;
use App\Events\DeploymentFailed;
use App\Events\DeploymentStarted;
use App\Listeners\LogDeployment;
use App\Models\Correction;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Listing;
use App\Models\Order;
use App\Models\Site;
use App\Modules\Deploy\Models\Deployment;
use App\Plugins\Reservations\Models\Reservation;
use App\Policies\CorrectionPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\DeploymentPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\ListingPolicy;
use App\Policies\OrderPolicy;
use App\Policies\ReservationPolicy;
use App\Policies\SitePolicy;
use App\Session\DatabaseSessionHandler;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Event;
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
        // Ręczne mapowanie policies, bo modele są w różnych namespace'ach
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(Site::class, SitePolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(Correction::class, CorrectionPolicy::class);
        Gate::policy(Listing::class, ListingPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(Deployment::class, DeploymentPolicy::class);
        Gate::policy(Reservation::class, ReservationPolicy::class);

        // Wczytaj credentials z .admin do config (jeśli plik istnieje)
        $adminFile = base_path('../.admin');
        if (file_exists($adminFile)) {
            $adminVars = [];
            $lines = file($adminFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                // Pomiń komentarze
                if (str_starts_with(trim($line), '#')) {
                    continue;
                }
                // Parsuj VAR="value" lub VAR=value
                if (preg_match('/^([A-Z_]+)=(.*)$/', $line, $matches)) {
                    $key = $matches[1];
                    $value = trim($matches[2], '"\'');
                    $adminVars[$key] = $value;
                }
            }

            // Ustaw OVH credentials w config jeśli nie są w .env
            if (isset($adminVars['OVH_APP_KEY']) && ! env('OVH_APP_KEY')) {
                config(['ovh.app_key' => $adminVars['OVH_APP_KEY']]);
            }
            if (isset($adminVars['OVH_APP_SECRET']) && ! env('OVH_APP_SECRET')) {
                config(['ovh.app_secret' => $adminVars['OVH_APP_SECRET']]);
            }
            if (isset($adminVars['OVH_CUSTOMER_KEY']) && ! env('OVH_CUSTOMER_KEY')) {
                config(['ovh.customer_key' => $adminVars['OVH_CUSTOMER_KEY']]);
            }

            // Ustaw VPS credentials w config jeśli nie są w .env
            if (isset($adminVars['SSH_VPS']) && ! env('SSH_VPS')) {
                config(['vps.ssh_host' => $adminVars['SSH_VPS']]);
            }
            if (isset($adminVars['VPS_IP']) && ! env('VPS_IP')) {
                config(['vps.ip' => $adminVars['VPS_IP']]);
            }
            if (isset($adminVars['VPS_WWW']) && ! env('VPS_WWW')) {
                config(['vps.www_root' => $adminVars['VPS_WWW']]);
            }
        }

        // Wymuszenie APP_URL dla generowania URL-i
        // Niezbędne gdy aplikacja działa za proxy na niestandardowym porcie
        $appUrl = config('app.url');
        if ($appUrl) {
            URL::forceRootUrl($appUrl);
        }

        // Rejestruj custom DatabaseSessionHandler dla sesji w bazie danych
        // WYŁĄCZONE - powoduje problemy z UUID (users.id to UUID, sessions.user_id to integer)
        // TODO: Zmienić sessions.user_id na UUID w migracji lub użyć standardowego handlera
        if (config('session.driver') === 'database' && config('session.use_custom_handler', false)) {
            Session::extend('database', function (Application $app) {
                $connection = $app['db']->connection(config('session.connection'));
                $table = config('session.table', 'sessions');
                $lifetime = config('session.lifetime', 120);

                return new DatabaseSessionHandler($connection, $table, $lifetime, $app);
            });
        }

        // Uwaga: Aktualizacja user_id w sesji jest obsługiwana przez UpdateSessionUserId middleware
        // oraz przez DatabaseSessionHandler::write(). Event SessionSaved nie istnieje w Laravel 11.

        // Rejestruj eventy deploymentu
        Event::listen(DeploymentStarted::class, [LogDeployment::class, 'handleStarted']);
        Event::listen(DeploymentCompleted::class, [LogDeployment::class, 'handleCompleted']);
        Event::listen(DeploymentFailed::class, [LogDeployment::class, 'handleFailed']);
        
        // Register observers for content revalidation
        if (class_exists(\App\Modules\Content\Models\ContentBlock::class)) {
            \App\Modules\Content\Models\ContentBlock::observe(\App\Observers\ContentBlockObserver::class);
        }
        
        if (class_exists(\App\Modules\Content\Models\SiteContent::class)) {
            \App\Modules\Content\Models\SiteContent::observe(\App\Observers\SiteContentObserver::class);
        }
        
        if (class_exists(\App\Modules\Generator\Models\Template::class)) {
            \App\Modules\Generator\Models\Template::observe(\App\Observers\TemplateObserver::class);
        }
        
        // Configure rate limiting
        \Illuminate\Support\Facades\RateLimiter::for('api', function ($request) {
            // Rate limit per tenant if available
            $key = $request->tenant?->id ?? $request->ip();
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(300)->by($key);
        });
    }
}
