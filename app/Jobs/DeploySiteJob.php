<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Site;
use App\Models\SiteEnvironment;
use App\Modules\Deploy\Models\Deployment;
use App\Modules\Deploy\Services\OVHService;
use App\Modules\Deploy\Services\VPSService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job do deploymentu strony na VPS.
 *
 * Obsługuje:
 * - Upload plików na VPS
 * - Konfiguracja Nginx
 * - Konfiguracja DNS (OVH)
 * - PM2 dla Next.js SSR
 * - Health check
 */
class DeploySiteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Maksymalna liczba prób.
     */
    public int $tries = 3;

    /**
     * Timeout w sekundach.
     */
    public int $timeout = 600;

    /**
     * Środowisko docelowe.
     */
    protected string $environment;

    /**
     * Typ deploymentu (static, ssr).
     */
    protected string $deployType;

    /**
     * Utworzenie nowej instancji Job.
     *
     * @param Site $site Strona do wdrożenia
     * @param string $environment Środowisko (staging|production)
     * @param string $deployType Typ (static|ssr)
     * @param string|null $sourcePath Ścieżka źródłowa (lokalna)
     */
    public function __construct(
        protected Site $site,
        string $environment = 'staging',
        string $deployType = 'static',
        protected ?string $sourcePath = null
    ) {
        $this->environment = $environment;
        $this->deployType = $deployType;
    }

    /**
     * Wykonanie job.
     */
    public function handle(VPSService $vps, OVHService $ovh): void
    {
        $deployment = $this->createDeployment();

        try {
            $deployment->addLog("Rozpoczęcie deploymentu dla {$this->site->name}");
            $deployment->addLog("Środowisko: {$this->environment}, Typ: {$this->deployType}");

            // 1. Przygotuj domenę
            $domain = $this->getDomain();
            $deployment->addLog("Domena: {$domain}");

            // 2. Konfiguracja DNS (jeśli potrzebna)
            $this->configureDNS($ovh, $domain, $deployment);

            // 3. Utwórz katalog na VPS
            $remotePath = $this->getRemotePath($domain);
            $deployment->addLog("Ścieżka zdalna: {$remotePath}");

            if (!$vps->createDirectory($remotePath)) {
                throw new \RuntimeException("Nie udało się utworzyć katalogu {$remotePath}");
            }
            $deployment->addLog('Katalog utworzony');

            // 4. Upload plików
            if ($this->sourcePath) {
                $deployment->addLog("Uploadowanie plików z {$this->sourcePath}...");

                if (!$vps->uploadFiles($this->sourcePath, $remotePath)) {
                    throw new \RuntimeException('Nie udało się uploadować plików');
                }
                $deployment->addLog('Pliki uploadowane');
            }

            // 5. Konfiguracja Nginx
            $this->configureNginx($vps, $domain, $remotePath, $deployment);

            // 6. Konfiguracja PM2 dla SSR
            if ($this->deployType === 'ssr') {
                $this->configureSSR($vps, $domain, $remotePath, $deployment);
            }

            // 7. Health check
            $this->performHealthCheck($vps, $domain, $deployment);

            // 8. Aktualizacja Site
            $this->updateSiteUrls($domain);

            // 9. Sukces
            $deployment->update([
                'status' => Deployment::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);
            $deployment->addLog('Deployment zakończony sukcesem!', 'success');

            Log::info('Deployment completed', [
                'site_id' => $this->site->id,
                'domain' => $domain,
                'environment' => $this->environment,
            ]);

        } catch (\Throwable $e) {
            $deployment->update([
                'status' => Deployment::STATUS_FAILED,
                'completed_at' => now(),
            ]);
            $deployment->addLog("BŁĄD: {$e->getMessage()}", 'error');

            Log::error('Deployment failed', [
                'site_id' => $this->site->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Tworzy rekord Deployment.
     */
    protected function createDeployment(): Deployment
    {
        // Binduj tenant
        app()->instance('current_tenant', $this->site->customer->tenant ?? null);

        return Deployment::create([
            'tenant_id' => $this->site->customer->tenant_id ?? null,
            'project_id' => $this->site->id,
            'status' => Deployment::STATUS_IN_PROGRESS,
            'version' => date('Ymd-His'),
            'logs' => [],
            'metadata' => [
                'environment' => $this->environment,
                'deploy_type' => $this->deployType,
                'source_path' => $this->sourcePath,
            ],
            'started_at' => now(),
        ]);
    }

    /**
     * Pobiera domenę dla środowiska.
     */
    protected function getDomain(): string
    {
        if ($this->environment === 'production') {
            // Użyj primary domain lub production_url
            $domain = $this->site->primaryDomain?->domain
                ?? parse_url($this->site->production_url ?? '', PHP_URL_HOST)
                ?? "{$this->site->slug}.octadecimal.studio";
        } else {
            // Staging: {slug}-api.example.test
            $domain = "{$this->site->slug}-api.example.test";
        }

        return $domain;
    }

    /**
     * Pobiera ścieżkę zdalną.
     */
    protected function getRemotePath(string $domain): string
    {
        return "/var/www/{$domain}";
    }

    /**
     * Konfiguruje DNS przez OVH.
     */
    protected function configureDNS(OVHService $ovh, string $domain, Deployment $deployment): void
    {
        // Sprawdź czy domena jest w octadecimal.studio
        if (!str_ends_with($domain, '.octadecimal.studio')) {
            $deployment->addLog("DNS: Pominięto (zewnętrzna domena {$domain})");
            return;
        }

        $subdomain = str_replace('.octadecimal.studio', '', $domain);
        $vpsIp = config('vps.ip', env('VPS_IP', '203.0.113.10'));

        try {
            $ovh->createARecord('octadecimal.studio', $subdomain, $vpsIp);
            $deployment->addLog("DNS: Utworzono rekord A dla {$subdomain} -> {$vpsIp}");
        } catch (\RuntimeException $e) {
            // Rekord może już istnieć
            $deployment->addLog("DNS: {$e->getMessage()} (prawdopodobnie istnieje)");
        }
    }

    /**
     * Konfiguruje Nginx.
     */
    protected function configureNginx(VPSService $vps, string $domain, string $remotePath, Deployment $deployment): void
    {
        if ($this->deployType === 'static') {
            $config = $vps->generateStaticNginxConfig($domain, $remotePath);
        } else {
            // SSR - Node.js proxy
            $port = $this->getNodePort();
            $config = $vps->generateNextJSNginxConfig($domain, $port);
        }

        $deployment->addLog('Konfigurowanie Nginx...');

        if (!$vps->manageNginx($domain, $config)) {
            throw new \RuntimeException('Nie udało się skonfigurować Nginx');
        }

        $deployment->addLog('Nginx skonfigurowany');
    }

    /**
     * Konfiguruje SSR (PM2).
     */
    protected function configureSSR(VPSService $vps, string $domain, string $remotePath, Deployment $deployment): void
    {
        $deployment->addLog('Konfigurowanie Node.js (PM2)...');

        $port = $this->getNodePort();

        if (!$vps->setupNodeJSApp($domain, $remotePath, $port)) {
            throw new \RuntimeException('Nie udało się uruchomić aplikacji przez PM2');
        }

        $deployment->addLog("PM2: Aplikacja uruchomiona na porcie {$port}");
    }

    /**
     * Wykonuje health check.
     */
    protected function performHealthCheck(VPSService $vps, string $domain, Deployment $deployment): void
    {
        $deployment->addLog('Wykonywanie health check...');

        // Poczekaj na propagację
        sleep(5);

        $url = "http://{$domain}/health";

        if (!$vps->healthCheck($url, 30)) {
            // Spróbuj jeszcze raz
            sleep(10);

            if (!$vps->healthCheck($url, 30)) {
                $deployment->addLog('Health check: Strona nie odpowiada (może wymagać SSL)', 'warning');
                return;
            }
        }

        $deployment->addLog('Health check: OK');
    }

    /**
     * Aktualizuje URL-e w Site.
     */
    protected function updateSiteUrls(string $domain): void
    {
        $url = "https://{$domain}";

        if ($this->environment === 'production') {
            $this->site->update([
                'production_url' => $url,
                'status' => 'live',
            ]);
        } else {
            $this->site->update([
                'staging_url' => $url,
                'status' => 'staging',
            ]);
        }
    }

    /**
     * Pobiera port dla Node.js (unikalne per site).
     */
    protected function getNodePort(): int
    {
        // Bazowy port + hash z site ID
        $base = 3000;
        $offset = crc32($this->site->id) % 1000;

        return $base + $offset;
    }

    /**
     * Obsługa błędu job.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('DeploySiteJob failed permanently', [
            'site_id' => $this->site->id,
            'environment' => $this->environment,
            'error' => $exception->getMessage(),
        ]);

        // Aktualizuj status site
        $this->site->update(['status' => 'development']);
    }
}
