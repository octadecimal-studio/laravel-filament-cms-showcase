<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Deploy\Services\SSLService;
use App\Modules\Deploy\Services\VPSService;
use Illuminate\Console\Command;

/**
 * Komenda do sprawdzania i odnowienia certyfikatów SSL.
 *
 * Uruchamiana codziennie przez scheduler.
 */
class SSLCheckRenewal extends Command
{
    /**
     * Sygnatura komendy.
     *
     * @var string
     */
    protected $signature = 'ssl:check-renewal';

    /**
     * Opis komendy.
     *
     * @var string
     */
    protected $description = 'Sprawdza i odnawia certyfikaty SSL, które wygasają wkrótce';

    /**
     * Wykonaj komendę.
     */
    public function handle(SSLService $sslService): int
    {
        $this->info('🔒 Sprawdzanie certyfikatów SSL...');

        $result = $sslService->renewExpiringCertificates();

        if (! $result['success']) {
            $this->error('❌ Nie udało się sprawdzić certyfikatów: '.$result['message']);

            return Command::FAILURE;
        }

        if (empty($result['renewed']) && empty($result['failed'])) {
            $this->info('✅ Wszystkie certyfikaty są aktualne ('.$result['total'].' certyfikatów)');

            return Command::SUCCESS;
        }

        if (! empty($result['renewed'])) {
            $this->info('✅ Odnowiono certyfikaty: '.implode(', ', $result['renewed']));
        }

        if (! empty($result['failed'])) {
            $this->warn('⚠️  Nie udało się odnowić: '.implode(', ', $result['failed']));
        }

        return Command::SUCCESS;
    }
}
