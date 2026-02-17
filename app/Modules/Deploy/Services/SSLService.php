<?php

declare(strict_types=1);

namespace App\Modules\Deploy\Services;

use Illuminate\Support\Facades\Log;

/**
 * Serwis do zarządzania certyfikatami SSL przez Certbot.
 */
final class SSLService
{
    private VPSService $vpsService;

    public function __construct(VPSService $vpsService)
    {
        $this->vpsService = $vpsService;
    }

    /**
     * Żąda certyfikatu SSL dla domeny.
     *
     * @param  array<string>  $altDomains
     * @return array<string, mixed>
     */
    public function requestCertificate(string $domain, array $altDomains = [], string $email = ''): array
    {
        if (empty($email)) {
            $email = config('mail.from.address', 'admin@octadecimal.studio');
        }

        $domains = array_merge([$domain], $altDomains);
        $domainsList = implode(' -d ', array_map('escapeshellarg', $domains));

        // Sprawdź czy certyfikat już istnieje
        if ($this->certificateExists($domain)) {
            return [
                'success' => true,
                'message' => "Certyfikat SSL dla {$domain} już istnieje",
                'domain' => $domain,
            ];
        }

        // Generuj certyfikat przez Certbot
        $command = "sudo certbot --nginx -d {$domainsList} --non-interactive --agree-tos --email {$email} --redirect";
        $result = $this->vpsService->executeCommand($command, true);

        if ($result['exit_code'] !== 0) {
            Log::error('SSL Certificate request failed', [
                'domain' => $domain,
                'output' => $result['output'],
            ]);

            return [
                'success' => false,
                'message' => "Nie udało się wygenerować certyfikatu SSL dla {$domain}",
                'error' => $result['output'],
                'domain' => $domain,
            ];
        }

        // Sprawdź datę wygaśnięcia
        $expiryDate = $this->getCertificateExpiryDate($domain);

        return [
            'success' => true,
            'message' => "Certyfikat SSL dla {$domain} został wygenerowany pomyślnie",
            'domain' => $domain,
            'expires_at' => $expiryDate?->toIso8601String(),
        ];
    }

    /**
     * Odnawia certyfikat SSL.
     */
    public function renewCertificate(string $domain): bool
    {
        $command = "sudo certbot renew --cert-name {$domain} --quiet";
        $result = $this->vpsService->executeCommand($command, true);

        if ($result['exit_code'] !== 0) {
            Log::error('SSL Certificate renewal failed', [
                'domain' => $domain,
                'output' => $result['output'],
            ]);

            return false;
        }

        // Przeładuj Nginx po odnowieniu
        $this->vpsService->executeCommand('sudo systemctl reload nginx', true);

        return true;
    }

    /**
     * Sprawdza datę wygaśnięcia certyfikatu.
     *
     * @return \Carbon\Carbon|null
     */
    public function checkCertificateExpiry(string $domain): ?int
    {
        $expiryDate = $this->getCertificateExpiryDate($domain);

        if ($expiryDate === null) {
            return null;
        }

        return $expiryDate->diffInDays(now());
    }

    /**
     * Sprawdza czy certyfikat wygasa wkrótce (< 30 dni).
     */
    public function isExpiringSoon(string $domain): bool
    {
        $daysUntilExpiry = $this->checkCertificateExpiry($domain);

        return $daysUntilExpiry !== null && $daysUntilExpiry < 30;
    }

    /**
     * Sprawdza czy certyfikat istnieje.
     */
    public function certificateExists(string $domain): bool
    {
        $command = "sudo certbot certificates 2>/dev/null | grep -q '{$domain}'";
        $result = $this->vpsService->executeCommand($command, true);

        return $result['exit_code'] === 0;
    }

    /**
     * Pobiera datę wygaśnięcia certyfikatu.
     *
     * @return \Carbon\Carbon|null
     */
    private function getCertificateExpiryDate(string $domain): ?\Carbon\Carbon
    {
        $command = "sudo certbot certificates 2>/dev/null | grep -A 5 '{$domain}' | grep 'Expiry Date' | sed 's/.*Expiry Date: //'";
        $result = $this->vpsService->executeCommand($command, true);

        if ($result['exit_code'] !== 0 || empty(trim($result['output']))) {
            return null;
        }

        try {
            $expiryString = trim($result['output']);
            // Format: "2026-01-22 21:30:00+00:00 (VALID: 89 days)"
            // Wyciągnij tylko datę
            $datePart = explode(' ', $expiryString)[0] ?? null;
            if ($datePart) {
                return \Carbon\Carbon::parse($datePart);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to parse certificate expiry date', [
                'domain' => $domain,
                'output' => $result['output'],
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Odnawia wszystkie certyfikaty, które wygasają wkrótce.
     *
     * @return array<string, mixed>
     */
    public function renewExpiringCertificates(): array
    {
        $command = "sudo certbot certificates 2>/dev/null | grep 'Certificate Name:' | awk '{print \$3}'";
        $result = $this->vpsService->executeCommand($command, true);

        if ($result['exit_code'] !== 0) {
            return [
                'success' => false,
                'message' => 'Nie można pobrać listy certyfikatów',
            ];
        }

        $certificates = array_filter(array_map('trim', explode("\n", $result['output'])));
        $renewed = [];
        $failed = [];

        foreach ($certificates as $certName) {
            if (empty($certName)) {
                continue;
            }

            if ($this->isExpiringSoon($certName)) {
                if ($this->renewCertificate($certName)) {
                    $renewed[] = $certName;
                } else {
                    $failed[] = $certName;
                }
            }
        }

        return [
            'success' => true,
            'renewed' => $renewed,
            'failed' => $failed,
            'total' => count($certificates),
        ];
    }
}
