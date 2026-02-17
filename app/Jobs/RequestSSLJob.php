<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Modules\Deploy\Models\Domain;
use App\Modules\Deploy\Services\SSLService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job do żądania certyfikatu SSL przez Certbot.
 *
 * Wrapper dla SSLService - generuje certyfikat SSL dla domeny.
 */
class RequestSSLJob implements ShouldQueue
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
    public $timeout = 300; // 5 minut

    /**
     * Utwórz nowy job.
     */
    public function __construct(
        public string $domainId,
        public string $domain,
        public array $altDomains = []
    ) {
    }

    /**
     * Wykonaj job.
     */
    public function handle(SSLService $sslService): void
    {
        $domainModel = Domain::findOrFail($this->domainId);

        try {
            // Aktualizuj status SSL
            $domainModel->update([
                'ssl_status' => Domain::SSL_STATUS_REQUESTED,
            ]);

            // Żądaj certyfikatu
            $result = $sslService->requestCertificate(
                $this->domain,
                $this->altDomains,
                config('ssl.certbot_email', config('mail.from.address'))
            );

            if (! $result['success']) {
                throw new \RuntimeException($result['message'] ?? 'Nie udało się wygenerować certyfikatu SSL');
            }

            // Aktualizuj status i datę wygaśnięcia
            $expiresAt = isset($result['expires_at']) ? \Carbon\Carbon::parse($result['expires_at']) : null;

            $domainModel->update([
                'ssl_status' => Domain::SSL_STATUS_ACTIVE,
                'ssl_expires_at' => $expiresAt,
            ]);
        } catch (\Exception $e) {
            Log::error('SSL certificate request failed', [
                'domain_id' => $this->domainId,
                'error' => $e->getMessage(),
            ]);

            $domainModel->update([
                'ssl_status' => Domain::SSL_STATUS_FAILED,
            ]);

            // Retry jeśli to błąd DNS (certyfikat może być wygenerowany później)
            if (str_contains($e->getMessage(), 'DNS') || str_contains($e->getMessage(), 'propagation')) {
                // Opóźnij retry o 5 minut
                $this->release(300);
            } else {
                throw $e;
            }
        }
    }

    /**
     * Obsługa niepowodzenia joba.
     */
    public function failed(\Throwable $exception): void
    {
        $domainModel = Domain::find($this->domainId);

        if ($domainModel) {
            $domainModel->update([
                'ssl_status' => Domain::SSL_STATUS_FAILED,
            ]);
        }
    }
}
