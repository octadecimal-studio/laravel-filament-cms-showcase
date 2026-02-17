<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Modules\Deploy\Models\Domain;
use App\Modules\Deploy\Services\OVHService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job do konfiguracji DNS przez OVH API.
 *
 * Wrapper dla OVHService - konfiguruje rekordy DNS dla domeny.
 */
class ConfigureDNSJob implements ShouldQueue
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
        public string $subdomain,
        public string $ip
    ) {
    }

    /**
     * Wykonaj job.
     */
    public function handle(OVHService $ovhService): void
    {
        $domainModel = Domain::findOrFail($this->domainId);

        try {
            // Aktualizuj status DNS
            $domainModel->update([
                'dns_status' => Domain::DNS_STATUS_PROPAGATING,
            ]);

            // Utwórz rekord A dla subdomeny
            $ovhService->createARecord($this->domain, $this->subdomain, $this->ip);

            // Opcjonalnie: rekord CNAME dla www
            if ($this->subdomain !== 'www') {
                try {
                    $ovhService->createCNAMERecord($this->domain, 'www', "{$this->subdomain}.{$this->domain}");
                } catch (\Exception $e) {
                    Log::warning('Failed to create CNAME record for www', [
                        'domain' => $this->domain,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Sprawdź propagację DNS (opcjonalnie, może być w osobnym jobie)
            // $propagation = $ovhService->checkPropagation("{$this->subdomain}.{$this->domain}");

            // Aktualizuj status
            $domainModel->update([
                'dns_status' => Domain::DNS_STATUS_ACTIVE,
                'dns_checked_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('DNS configuration failed', [
                'domain_id' => $this->domainId,
                'error' => $e->getMessage(),
            ]);

            $domainModel->update([
                'dns_status' => Domain::DNS_STATUS_FAILED,
            ]);

            throw $e;
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
                'dns_status' => Domain::DNS_STATUS_FAILED,
            ]);
        }
    }
}
