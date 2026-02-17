<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ConfigureDNSJob;
use App\Jobs\DeployProjectJob;
use App\Jobs\RequestSSLJob;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Scopes\TenantScope;
use App\Modules\Deploy\Models\Deployment;
use App\Modules\Deploy\Models\Domain;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Queue;

/**
 * Komenda do wdrożenia szablonu na subdomenę przy użyciu Deployment Pipeline.
 */
class DeployTemplate extends Command
{
    /**
     * Sygnatura komendy.
     *
     * @var string
     */
    protected $signature = 'deploy:template 
                            {subdomain : Subdomena (np. 2whells-dev)}
                            {domain : Domena główna (np. octadecimal.studio)}
                            {template_path : Ścieżka do szablonu (np. templates/motorent-demo/out)}
                            {--tenant= : UUID tenanta (opcjonalnie - użyje pierwszego aktywnego)}
                            {--skip-dns : Pomiń konfigurację DNS}
                            {--skip-ssl : Pomiń generowanie SSL}';

    /**
     * Opis komendy.
     *
     * @var string
     */
    protected $description = 'Wdraża szablon na subdomenę przy użyciu Deployment Pipeline';

    /**
     * Wykonaj komendę.
     */
    public function handle(): int
    {
        $subdomain = $this->argument('subdomain');
        $domain = $this->argument('domain');
        $templatePath = $this->argument('template_path');
        $fullDomain = "{$subdomain}.{$domain}";

        $this->info("🚀 Rozpoczynam wdrożenie szablonu na {$fullDomain}...");

        // 1. Pobierz lub utwórz tenanta
        $tenant = $this->getTenant();
        if (! $tenant) {
            // Jeśli nie ma dostępu do bazy, użyj domyślnego UUID (dla lokalnego wdrożenia)
            $this->warn('⚠️  Nie można pobrać tenanta z bazy. Używam domyślnego UUID.');
            $this->warn('💡 Aby użyć prawdziwego tenanta, uruchom z dostępem do bazy lub użyj --tenant=UUID');
            
            // Dla lokalnego wdrożenia, użyj hardcoded UUID (można później zmienić)
            // W rzeczywistości, dla deploymentu bez bazy, możemy pominąć tenant_id
            // ale modele wymagają tenant_id, więc użyjemy placeholder
            $tenantId = '00000000-0000-0000-0000-000000000000';
        } else {
            $tenantId = $tenant->id;
            $this->info("✅ Używam tenanta: {$tenant->name} ({$tenantId})");
        }

        // 2. Sprawdź czy szablon istnieje
        $absoluteTemplatePath = base_path($templatePath);
        if (! is_dir($absoluteTemplatePath)) {
            $this->error("❌ Szablon nie istnieje: {$absoluteTemplatePath}");

            return Command::FAILURE;
        }

        $this->info("✅ Znaleziono szablon: {$absoluteTemplatePath}");

        // 3. Utwórz Domain (bez global scope dla komendy artisan)
        try {
            $domainModel = Domain::withoutGlobalScope(TenantScope::class)->firstOrCreate(
                [
                    'domain' => $fullDomain,
                ],
                [
                    'tenant_id' => $tenantId,
                    'subdomain' => $subdomain,
                    'domain' => $fullDomain,
                    'dns_status' => Domain::DNS_STATUS_PENDING,
                    'ssl_status' => Domain::SSL_STATUS_PENDING,
                    'vps_ip' => config('vps.ip', '203.0.113.10'),
                ]
            );

            $this->info("✅ Utworzono/zaktualizowano domenę: {$domainModel->id}");

            // 4. Utwórz Deployment
            $deployment = Deployment::withoutGlobalScope(TenantScope::class)->create([
                'tenant_id' => $tenantId,
                'domain_id' => $domainModel->id,
                'status' => Deployment::STATUS_PENDING,
                'version' => date('Ymd-His'),
                'metadata' => [
                    'template_path' => $templatePath,
                    'subdomain' => $subdomain,
                    'domain' => $domain,
                ],
            ]);
        } catch (\Exception $e) {
            $this->error("❌ Błąd podczas tworzenia Domain/Deployment: {$e->getMessage()}");
            $this->warn("💡 Próbuję wdrożyć bezpośrednio bez bazy danych...");
            
            // Fallback: wdrożenie bezpośrednio bez bazy
            return $this->deployDirectly($fullDomain, $absoluteTemplatePath, $subdomain, $domain);
        }

        $this->info("✅ Utworzono deployment: {$deployment->id}");

        // 5. Konfiguracja DNS
        if (! $this->option('skip-dns')) {
            $this->info('🌐 Konfiguruję DNS...');
            ConfigureDNSJob::dispatch(
                $domainModel->id,
                $domain,
                $subdomain,
                config('vps.ip', '203.0.113.10')
            );
            $this->info('✅ Job DNS dodany do kolejki');
        } else {
            $this->warn('⏭️  Pominięto konfigurację DNS');
        }

        // 6. Deploy projektu
        $this->info('📤 Wdrażam projekt...');
        DeployProjectJob::dispatch(
            $deployment->id,
            $fullDomain,
            $absoluteTemplatePath,
            $deployment->version
        );
        $this->info('✅ Job deployment dodany do kolejki');

        // 7. Generowanie SSL
        if (! $this->option('skip-ssl')) {
            $this->info('🔒 Generuję certyfikat SSL...');
            RequestSSLJob::dispatch(
                $domainModel->id,
                $fullDomain
            );
            $this->info('✅ Job SSL dodany do kolejki');
        } else {
            $this->warn('⏭️  Pominięto generowanie SSL');
        }

        $this->newLine();
        $this->info("✅ Wdrożenie zostało zaplanowane!");
        $this->info("📊 Deployment ID: {$deployment->id}");
        $this->info("🌐 Domena: {$fullDomain}");
        $this->info("📝 Sprawdź status w Filament: /admin/deployments");

        return Command::SUCCESS;
    }

    /**
     * Wdrożenie bezpośrednie bez bazy danych (fallback).
     */
    private function deployDirectly(string $fullDomain, string $templatePath, string $subdomain, string $domain): int
    {
        $this->info('🚀 Wdrażam bezpośrednio bez bazy danych...');

        $vpsService = app(\App\Modules\Deploy\Services\VPSService::class);
        $sslService = app(\App\Modules\Deploy\Services\SSLService::class);

        $vpsIp = config('vps.ip', '203.0.113.10');
        $remoteDir = config('vps.www_root', '/var/www').'/'.$fullDomain;
        $ovhScript = base_path('../scripts/automation/ovh-dns.sh');

        try {
            // 1. Konfiguracja DNS (używamy bash script)
            if (! $this->option('skip-dns')) {
                $this->info('🌐 Konfiguruję DNS...');
                
                // Użyj bash script zamiast OVHService (który wymaga cache)
                $command = "bash {$ovhScript} add {$subdomain} {$vpsIp} {$domain}";
                $output = [];
                $exitCode = 0;
                exec($command.' 2>&1', $output, $exitCode);
                
                if ($exitCode !== 0) {
                    $this->warn("⚠️  DNS może już istnieć lub wystąpił błąd: ".implode("\n", $output));
                } else {
                    $this->info('✅ DNS skonfigurowany');
                }
            }

            // 2. Utwórz katalog na VPS
            $this->info("📁 Tworzenie katalogu: {$remoteDir}");
            $vpsService->createDirectory($remoteDir);

            // 3. Wysyłanie plików
            $this->info('📤 Wysyłanie plików na VPS...');
            $uploadSuccess = $vpsService->uploadFiles($templatePath, $remoteDir);
            if (! $uploadSuccess) {
                throw new \RuntimeException('Nie udało się wysłać plików na VPS');
            }
            $this->info('✅ Pliki wysłane');

            // 4. Sprawdź typ aplikacji i skonfiguruj odpowiednio
            $this->info('🔍 Wykrywanie typu aplikacji...');
            
            // Sprawdź czy to Next.js SSR (sprawdź czy istnieje .next)
            $isNextJSSSR = is_dir($templatePath.'/.next');
            $isStatic = file_exists($templatePath.'/index.html') && !$isNextJSSSR;
            
            if ($isNextJSSSR) {
                // Next.js SSR - wymaga Node.js server
                $this->info('🚀 Wykryto Next.js SSR - konfiguruję Node.js server...');
                
                $port = 3000;
                
                // 4a. Setup Node.js app przez PM2
                $this->info('📦 Konfiguruję aplikację Node.js...');
                $nodeSetup = $vpsService->setupNodeJSApp($fullDomain, $remoteDir, $port);
                
                if (!$nodeSetup) {
                    $this->error('❌ Nie udało się skonfigurować aplikacji Node.js');
                    throw new \RuntimeException('Nie udało się skonfigurować aplikacji Node.js');
                }
                
                // 4b. Generuj konfigurację Nginx jako reverse proxy
                $nginxConfig = $vpsService->generateNextJSNginxConfig($fullDomain, $port);
                
                $this->info('✅ Aplikacja Node.js skonfigurowana');
            } elseif ($isStatic) {
                // Statyczny HTML
                $this->info('📄 Wykryto statyczny HTML...');
                $nginxConfig = $vpsService->generateStaticNginxConfig($fullDomain, $remoteDir);
            } else {
                // PHP/Laravel
                $this->info('🐘 Wykryto aplikację PHP...');
                $nginxConfig = $vpsService->generateNginxConfig($fullDomain, $remoteDir);
            }
            
            // 5. Konfiguracja Nginx
            $this->info('🌐 Konfiguracja Nginx...');
            $vpsService->manageNginx($fullDomain, $nginxConfig);
            $this->info('✅ Nginx skonfigurowany');

            // 5. SSL
            if (! $this->option('skip-ssl')) {
                $this->info('🔒 Generuję certyfikat SSL...');
                $result = $sslService->requestCertificate($fullDomain, [], config('ssl.certbot_email'));
                if ($result['success']) {
                    $this->info('✅ SSL wygenerowany');
                } else {
                    $this->warn("⚠️  SSL nie został wygenerowany: {$result['message']}");
                }
            }

            $this->newLine();
            $this->info("✅ Wdrożenie zakończone pomyślnie!");
            $this->info("🌐 URL: http://{$fullDomain}");
            if (! $this->option('skip-ssl')) {
                $this->info("🔒 HTTPS: https://{$fullDomain} (może wymagać propagacji DNS)");
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Błąd podczas wdrożenia: {$e->getMessage()}");
            
            return Command::FAILURE;
        }
    }

    /**
     * Pobiera tenanta (z argumentu lub pierwszego aktywnego).
     */
    private function getTenant(): ?Tenant
    {
        $tenantId = $this->option('tenant');

        if ($tenantId) {
            try {
                return Tenant::where('id', $tenantId)
                    ->where('is_active', true)
                    ->first();
            } catch (\Exception $e) {
                $this->warn("⚠️  Nie można połączyć się z bazą danych: {$e->getMessage()}");
                $this->warn("💡 Użyj --tenant=UUID lub uruchom lokalnie z dostępem do bazy");
                
                return null;
            }
        }

        // Pobierz pierwszego aktywnego tenanta
        try {
            return Tenant::where('is_active', true)->first();
        } catch (\Exception $e) {
            $this->warn("⚠️  Nie można połączyć się z bazą danych: {$e->getMessage()}");
            $this->warn("💡 Użyj --tenant=UUID lub uruchom lokalnie z dostępem do bazy");
            
            return null;
        }
    }
}
