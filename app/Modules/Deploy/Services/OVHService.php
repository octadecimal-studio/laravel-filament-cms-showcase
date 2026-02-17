<?php

declare(strict_types=1);

namespace App\Modules\Deploy\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Serwis do zarządzania DNS przez OVH API.
 *
 * Port z scripts/automation/ovh-dns.sh do PHP.
 */
final class OVHService
{
    private const ENDPOINT = 'https://eu.api.ovh.com/1.0';

    private string $appKey;
    private string $appSecret;
    private string $customerKey;

    public function __construct()
    {
        // Wczytaj credentials z .admin (fallback na .env)
        $this->appKey = config('ovh.app_key', env('OVH_APP_KEY'));
        $this->appSecret = config('ovh.app_secret', env('OVH_APP_SECRET'));
        $this->customerKey = config('ovh.customer_key', env('OVH_CUSTOMER_KEY'));

        if (empty($this->appKey) || empty($this->appSecret) || empty($this->customerKey)) {
            throw new \RuntimeException('OVH API credentials nie są skonfigurowane. Sprawdź config/ovh.php lub .env');
        }
    }

    /**
     * Generuje sygnaturę dla requestu OVH API.
     */
    private function generateSignature(string $method, string $url, string $body, string $timestamp): string
    {
        $toSign = "{$this->appSecret}+{$this->customerKey}+{$method}+{$url}+{$body}+{$timestamp}";
        $hash = sha1($toSign);

        return "\$1\${$hash}";
    }

    /**
     * Pobiera timestamp z OVH API.
     */
    private function getTimestamp(): string
    {
        $response = Http::get(self::ENDPOINT.'/auth/time');

        if (! $response->successful()) {
            throw new \RuntimeException('Nie można pobrać timestamp z OVH API');
        }

        return (string) $response->body();
    }

    /**
     * Wykonuje request do OVH API.
     *
     * @param  string  $method
     * @param  string  $endpoint
     * @param  array<string, mixed>|null  $body
     * @return array<string, mixed>
     */
    private function request(string $method, string $endpoint, ?array $body = null): array
    {
        $url = self::ENDPOINT.$endpoint;
        $timestamp = $this->getTimestamp();
        $bodyJson = $body !== null ? json_encode($body, JSON_UNESCAPED_SLASHES) : '';
        $signature = $this->generateSignature($method, $url, $bodyJson, $timestamp);

        $headers = [
            'Content-Type' => 'application/json; charset=utf-8',
            'X-Ovh-Application' => $this->appKey,
            'X-Ovh-Consumer' => $this->customerKey,
            'X-Ovh-Timestamp' => $timestamp,
            'X-Ovh-Signature' => $signature,
        ];

        $response = Http::withHeaders($headers);

        if ($body !== null) {
            $response = $response->send($method, $url, $body);
        } else {
            $response = $response->send($method, $url);
        }

        if (! $response->successful()) {
            $error = $response->json();
            $message = $error['message'] ?? $response->body();
            Log::error('OVH API Error', [
                'method' => $method,
                'endpoint' => $endpoint,
                'error' => $error,
            ]);

            throw new \RuntimeException("OVH API Error: {$message}");
        }

        return $response->json() ?? [];
    }

    /**
     * Listuje wszystkie rekordy DNS dla domeny.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listRecords(string $domain, ?string $type = null): array
    {
        $cacheKey = "ovh_dns_records_{$domain}_{$type}";

        return Cache::remember($cacheKey, 300, function () use ($domain, $type) {
            $recordIds = $this->request('GET', "/domain/zone/{$domain}/record");

            if (empty($recordIds)) {
                return [];
            }

            $records = [];
            foreach ($recordIds as $recordId) {
                $record = $this->request('GET', "/domain/zone/{$domain}/record/{$recordId}");

                // Filtruj po typie jeśli podano
                if ($type !== null && ($record['fieldType'] ?? '') !== $type) {
                    continue;
                }

                $records[] = [
                    'id' => $recordId,
                    'subdomain' => $record['subDomain'] ?? '',
                    'type' => $record['fieldType'] ?? '',
                    'target' => $record['target'] ?? '',
                    'ttl' => $record['ttl'] ?? 3600,
                ];
            }

            return $records;
        });
    }

    /**
     * Tworzy rekord A.
     *
     * @return array<string, mixed>
     */
    public function createARecord(string $domain, string $subdomain, string $ip, int $ttl = 3600): array
    {
        // Sprawdź czy rekord już istnieje
        if ($this->recordExists($domain, 'A', $subdomain, $ip)) {
            throw new \RuntimeException("Rekord A dla {$subdomain}.{$domain} już istnieje");
        }

        $body = [
            'fieldType' => 'A',
            'subDomain' => $subdomain,
            'target' => $ip,
            'ttl' => $ttl,
        ];

        $result = $this->request('POST', "/domain/zone/{$domain}/record", $body);

        // Odśwież strefę DNS
        $this->refreshZone($domain);

        // Inwaliduj cache
        Cache::forget("ovh_dns_records_{$domain}_");
        Cache::forget("ovh_dns_records_{$domain}_A");

        return $result;
    }

    /**
     * Tworzy rekord MX.
     *
     * @return array<string, mixed>
     */
    public function createMXRecord(string $domain, int $priority, string $target, int $ttl = 3600): array
    {
        // OVH wymaga formatu "priority target" dla rekordów MX
        $mxTarget = "{$priority} {$target}";
        if (str_ends_with($target, ".{$domain}") && ! str_ends_with($target, '.')) {
            $mxTarget = "{$priority} {$target}.";
        }

        // Sprawdź czy rekord już istnieje
        if ($this->recordExists($domain, 'MX', '', $mxTarget)) {
            throw new \RuntimeException("Rekord MX dla {$domain} już istnieje");
        }

        $body = [
            'fieldType' => 'MX',
            'subDomain' => '',
            'target' => $mxTarget,
            'ttl' => $ttl,
        ];

        $result = $this->request('POST', "/domain/zone/{$domain}/record", $body);

        // Odśwież strefę DNS
        $this->refreshZone($domain);

        // Inwaliduj cache
        Cache::forget("ovh_dns_records_{$domain}_");
        Cache::forget("ovh_dns_records_{$domain}_MX");

        return $result;
    }

    /**
     * Tworzy rekord TXT.
     *
     * @return array<string, mixed>
     */
    public function createTXTRecord(string $domain, string $subdomain, string $value, int $ttl = 3600): array
    {
        // Sprawdź czy rekord już istnieje
        if ($this->recordExists($domain, 'TXT', $subdomain, $value)) {
            throw new \RuntimeException("Rekord TXT dla {$subdomain}.{$domain} już istnieje");
        }

        $body = [
            'fieldType' => 'TXT',
            'subDomain' => $subdomain,
            'target' => $value,
            'ttl' => $ttl,
        ];

        $result = $this->request('POST', "/domain/zone/{$domain}/record", $body);

        // Odśwież strefę DNS
        $this->refreshZone($domain);

        // Inwaliduj cache
        Cache::forget("ovh_dns_records_{$domain}_");
        Cache::forget("ovh_dns_records_{$domain}_TXT");

        return $result;
    }

    /**
     * Usuwa rekord DNS.
     */
    public function deleteRecord(string $domain, int $recordId): bool
    {
        $this->request('DELETE', "/domain/zone/{$domain}/record/{$recordId}");

        // Odśwież strefę DNS
        $this->refreshZone($domain);

        // Inwaliduj cache
        Cache::forget("ovh_dns_records_{$domain}_");

        return true;
    }

    /**
     * Sprawdza czy rekord już istnieje.
     */
    public function recordExists(string $domain, string $type, string $subdomain, string $target): bool
    {
        $records = $this->listRecords($domain, $type);

        foreach ($records as $record) {
            if (
                ($record['type'] ?? '') === $type &&
                ($record['subdomain'] ?? '') === $subdomain &&
                ($record['target'] ?? '') === $target
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Odświeża strefę DNS.
     */
    public function refreshZone(string $domain): bool
    {
        $this->request('POST', "/domain/zone/{$domain}/refresh");

        return true;
    }

    /**
     * Sprawdza propagację DNS.
     *
     * @param  array<string>  $dnsServers
     * @return array<string, string>
     */
    public function checkPropagation(string $domain, array $dnsServers = ['8.8.8.8', '1.1.1.1']): array
    {
        $results = [];

        foreach ($dnsServers as $server) {
            $command = "dig +short @{$server} {$domain}";
            $output = shell_exec($command);
            $results[$server] = trim($output ?? '');
        }

        return $results;
    }

    /**
     * Tworzy subdomenę (rekord A + opcjonalnie CNAME dla www).
     *
     * @return array<string, mixed>
     */
    public function createSubdomain(string $domain, string $subdomain, string $ip, bool $createWww = false): array
    {
        $result = [
            'a_record' => $this->createARecord($domain, $subdomain, $ip),
        ];

        if ($createWww) {
            try {
                $result['cname_record'] = $this->createCNAMERecord($domain, 'www', "{$subdomain}.{$domain}");
            } catch (\Exception $e) {
                Log::warning("Nie udało się utworzyć rekordu CNAME dla www.{$subdomain}.{$domain}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $result;
    }

    /**
     * Tworzy rekord CNAME.
     *
     * @return array<string, mixed>
     */
    public function createCNAMERecord(string $domain, string $subdomain, string $target, int $ttl = 3600): array
    {
        $body = [
            'fieldType' => 'CNAME',
            'subDomain' => $subdomain,
            'target' => $target,
            'ttl' => $ttl,
        ];

        $result = $this->request('POST', "/domain/zone/{$domain}/record", $body);

        // Odśwież strefę DNS
        $this->refreshZone($domain);

        // Inwaliduj cache
        Cache::forget("ovh_dns_records_{$domain}_");
        Cache::forget("ovh_dns_records_{$domain}_CNAME");

        return $result;
    }

    /**
     * Konfiguruje DNS dla email (A + MX + SPF + DKIM + DMARC).
     *
     * @return array<string, mixed>
     */
    public function configureEmailDNS(string $domain, string $mailHostname, string $vpsIp): array
    {
        $results = [];

        // 1. Rekord A dla mail hostname
        $subdomain = str_replace(".{$domain}", '', $mailHostname);
        try {
            $results['a_record'] = $this->createARecord($domain, $subdomain, $vpsIp);
        } catch (\Exception $e) {
            Log::warning("Rekord A dla {$mailHostname} może już istnieć", ['error' => $e->getMessage()]);
        }

        // 2. Rekord MX
        try {
            $results['mx_record'] = $this->createMXRecord($domain, 10, $mailHostname);
        } catch (\Exception $e) {
            Log::warning("Rekord MX dla {$domain} może już istnieć", ['error' => $e->getMessage()]);
        }

        // 3. Rekord SPF
        $spfValue = "v=spf1 mx a ip4:{$vpsIp} -all";
        try {
            $results['spf_record'] = $this->createTXTRecord($domain, '', $spfValue);
        } catch (\Exception $e) {
            Log::warning("Rekord SPF dla {$domain} może już istnieć", ['error' => $e->getMessage()]);
        }

        // 4. Rekord DMARC
        $dmarcValue = "v=DMARC1; p=reject; rua=mailto:dmarc@{$domain}";
        try {
            $results['dmarc_record'] = $this->createTXTRecord($domain, '_dmarc', $dmarcValue);
        } catch (\Exception $e) {
            Log::warning("Rekord DMARC dla {$domain} może już istnieć", ['error' => $e->getMessage()]);
        }

        // DKIM będzie dodany później przez Mailcow API

        return $results;
    }

    /**
     * Konfiguruje DNS dla projektu (subdomena + rekord A).
     *
     * @return array<string, mixed>
     */
    public function configureProjectDNS(string $domain, string $subdomain, string $ip): array
    {
        return $this->createSubdomain($domain, $subdomain, $ip, true);
    }
}
