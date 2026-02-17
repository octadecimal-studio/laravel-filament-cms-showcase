<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Modules\Deploy\Models\Deployment;
use App\Modules\Deploy\Services\VPSService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

/**
 * Job do wdrożenia projektu na VPS.
 *
 * Wrapper dla VPSService - wykonuje pełne wdrożenie projektu.
 */
class DeployProjectJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Liczba prób wykonania joba.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * Timeout w sekundach.
     *
     * @var int
     */
    public $timeout = 600; // 10 minut

    /**
     * Utwórz nowy job.
     */
    public function __construct(
        public string $deploymentId,
        public string $domain,
        public string $projectPath,
        public ?string $version = null
    ) {
        $this->version = $version ?? date('Ymd-His');
    }

    /**
     * Wykonaj job.
     */
    public function handle(VPSService $vpsService): void
    {
        $deployment = Deployment::findOrFail($this->deploymentId);

        try {
            // Aktualizuj status
            $deployment->update([
                'status' => Deployment::STATUS_IN_PROGRESS,
                'started_at' => now(),
                'version' => $this->version,
            ]);

            // Wywołaj event
            Event::dispatch(new \App\Events\DeploymentStarted($deployment));

            $deployment->addLog('🚀 Rozpoczęcie deploymentu...', 'info');

            // 1. Utwórz katalog na VPS
            $remoteDir = config('vps.www_root', '/var/www').'/'.$this->domain;
            $deployment->addLog("📁 Tworzenie katalogu: {$remoteDir}", 'info');
            $vpsService->createDirectory($remoteDir);

            // 2. Wysyłanie plików
            $deployment->addLog('📤 Wysyłanie plików na VPS...', 'info');
            
            // Sprawdź czy ścieżka istnieje
            if (! is_dir($this->projectPath)) {
                throw new \RuntimeException("Ścieżka do projektu nie istnieje: {$this->projectPath}");
            }

            // Wysyłamy pliki na VPS
            $uploadSuccess = $vpsService->uploadFiles($this->projectPath, $remoteDir);
            
            if (! $uploadSuccess) {
                throw new \RuntimeException('Nie udało się wysłać plików na VPS');
            }

            $deployment->addLog('✅ Pliki wysłane pomyślnie', 'info');

            // 3. Sprawdź typ aplikacji i skonfiguruj odpowiednio
            $deployment->addLog('🔍 Wykrywanie typu aplikacji...', 'info');
            
            // Sprawdź czy to Next.js SSR (sprawdź czy istnieje .next)
            $isNextJSSSR = is_dir($this->projectPath.'/.next');
            $isStatic = file_exists($this->projectPath.'/index.html') && !$isNextJSSSR;
            
            if ($isNextJSSSR) {
                // Next.js SSR - wymaga Node.js server
                $deployment->addLog('🚀 Wykryto Next.js SSR - konfiguruję Node.js server...', 'info');
                
                $port = 3000;
                
                // 3a. Setup Node.js app przez PM2
                $deployment->addLog('📦 Konfiguruję aplikację Node.js...', 'info');
                $nodeSetup = $vpsService->setupNodeJSApp($this->domain, $remoteDir, $port);
                
                if (!$nodeSetup) {
                    $deployment->addLog('❌ Nie udało się skonfigurować aplikacji Node.js', 'error');
                    throw new \RuntimeException('Nie udało się skonfigurować aplikacji Node.js');
                }
                
                // 3b. Generuj konfigurację Nginx jako reverse proxy
                $nginxConfig = $vpsService->generateNextJSNginxConfig($this->domain, $port);
                
                $deployment->addLog('✅ Aplikacja Node.js skonfigurowana', 'info');
            } elseif ($isStatic) {
                // Statyczny HTML
                $deployment->addLog('📄 Wykryto statyczny HTML...', 'info');
                $nginxConfig = $vpsService->generateStaticNginxConfig($this->domain, $remoteDir);
            } else {
                // PHP/Laravel
                $deployment->addLog('🐘 Wykryto aplikację PHP...', 'info');
                $nginxConfig = $vpsService->generateNginxConfig($this->domain, $remoteDir);
            }
            
            // 4. Konfiguracja Nginx
            $deployment->addLog('🌐 Konfiguracja Nginx...', 'info');
            $vpsService->manageNginx($this->domain, $nginxConfig);

            // 4. Reload Nginx
            $deployment->addLog('🔄 Przeładowanie Nginx...', 'info');
            $reloadResult = $vpsService->executeCommand('sudo nginx -t && sudo systemctl reload nginx', true);
            
            if ($reloadResult['exit_code'] !== 0) {
                throw new \RuntimeException('Nie udało się przeładować Nginx: '.$reloadResult['output']);
            }

            // 5. Health check (opcjonalnie - dla statycznego HTML może nie być /health)
            $deployment->addLog('🏥 Sprawdzanie health check...', 'info');
            $healthCheck = $vpsService->healthCheck("http://{$this->domain}/", 10);

            if (! $healthCheck) {
                $deployment->addLog('⚠️  Health check nie powiódł się, ale kontynuujemy...', 'warning');
            } else {
                $deployment->addLog('✅ Health check zakończony pomyślnie', 'info');
            }

            // Sukces
            $deployment->update([
                'status' => Deployment::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);

            // Wywołaj event
            Event::dispatch(new \App\Events\DeploymentCompleted($deployment));

            $deployment->addLog('✅ Deployment zakończony pomyślnie!', 'info');
        } catch (\Exception $e) {
            Log::error('Deployment failed', [
                'deployment_id' => $this->deploymentId,
                'error' => $e->getMessage(),
            ]);

            $deployment->update([
                'status' => Deployment::STATUS_FAILED,
                'completed_at' => now(),
            ]);

            // Wywołaj event
            Event::dispatch(new \App\Events\DeploymentFailed($deployment, $e));

            $deployment->addLog("❌ Błąd: {$e->getMessage()}", 'error');

            throw $e;
        }
    }

    /**
     * Obsługa niepowodzenia joba.
     */
    public function failed(\Throwable $exception): void
    {
        $deployment = Deployment::find($this->deploymentId);

        if ($deployment) {
            $deployment->update([
                'status' => Deployment::STATUS_FAILED,
                'completed_at' => now(),
            ]);

            $deployment->addLog("❌ Deployment zakończony niepowodzeniem: {$exception->getMessage()}", 'error');
        }
    }
}
