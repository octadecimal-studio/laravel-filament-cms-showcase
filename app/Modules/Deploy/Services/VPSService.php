<?php

declare(strict_types=1);

namespace App\Modules\Deploy\Services;

use Illuminate\Support\Facades\Log;

/**
 * Serwis do zarządzania VPS przez SSH.
 *
 * Port z scripts/deploy-full-vps.sh do PHP.
 */
final class VPSService
{
    private string $host;
    private string $user;
    private string $ip;
    private string $wwwRoot;

    public function __construct()
    {
        // Wczytaj konfigurację z .admin (fallback na .env)
        $sshVps = config('vps.ssh_host', env('SSH_VPS', 'debian@203.0.113.10'));
        
        // Parsuj user@host
        if (str_contains($sshVps, '@')) {
            [$this->user, $this->host] = explode('@', $sshVps, 2);
        } else {
            $this->user = 'debian';
            $this->host = $sshVps;
        }

        $this->ip = config('vps.ip', env('VPS_IP', '203.0.113.10'));
        $this->wwwRoot = config('vps.www_root', env('VPS_WWW', '/var/www'));
    }

    /**
     * Wykonuje komendę SSH na VPS.
     *
     * @return array{output: string, exit_code: int}
     */
    public function executeCommand(string $command, bool $sudo = false): array
    {
        $fullCommand = $sudo ? "sudo {$command}" : $command;
        $sshCommand = "ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 {$this->user}@{$this->host} '{$fullCommand}'";

        $output = [];
        $exitCode = 0;

        exec($sshCommand.' 2>&1', $output, $exitCode);

        $outputString = implode("\n", $output);

        if ($exitCode !== 0) {
            Log::warning('VPS Command failed', [
                'command' => $command,
                'exit_code' => $exitCode,
                'output' => $outputString,
            ]);
        }

        return [
            'output' => $outputString,
            'exit_code' => $exitCode,
        ];
    }

    /**
     * Wysyła pliki na VPS przez SCP.
     */
    public function uploadFiles(string $localPath, string $remotePath): bool
    {
        $scpCommand = "scp -o StrictHostKeyChecking=no -r '{$localPath}' {$this->user}@{$this->host}:{$remotePath}";

        $output = [];
        $exitCode = 0;

        exec($scpCommand.' 2>&1', $output, $exitCode);

        if ($exitCode !== 0) {
            Log::error('VPS Upload failed', [
                'local_path' => $localPath,
                'remote_path' => $remotePath,
                'exit_code' => $exitCode,
                'output' => implode("\n", $output),
            ]);

            return false;
        }

        return true;
    }

    /**
     * Pobiera pliki z VPS przez SCP.
     */
    public function downloadFiles(string $remotePath, string $localPath): bool
    {
        $scpCommand = "scp -o StrictHostKeyChecking=no -r {$this->user}@{$this->host}:{$remotePath} '{$localPath}'";

        $output = [];
        $exitCode = 0;

        exec($scpCommand.' 2>&1', $output, $exitCode);

        if ($exitCode !== 0) {
            Log::error('VPS Download failed', [
                'remote_path' => $remotePath,
                'local_path' => $localPath,
                'exit_code' => $exitCode,
                'output' => implode("\n", $output),
            ]);

            return false;
        }

        return true;
    }

    /**
     * Zarządza konfiguracją Nginx.
     */
    public function manageNginx(string $domain, string $config): bool
    {
        // Zapisz konfigurację na VPS
        $configPath = "/etc/nginx/sites-available/{$domain}";
        $tempFile = sys_get_temp_dir().'/nginx-'.uniqid().'.conf';

        file_put_contents($tempFile, $config);

        // Wysyłamy plik na VPS
        if (! $this->uploadFiles($tempFile, $tempFile)) {
            unlink($tempFile);

            return false;
        }

        // Przenieś plik do właściwej lokalizacji
        $result = $this->executeCommand("sudo mv {$tempFile} {$configPath}", true);

        // Usuń lokalny plik
        unlink($tempFile);

        if ($result['exit_code'] !== 0) {
            return false;
        }

        // Aktywuj konfigurację
        $this->executeCommand("sudo ln -sf {$configPath} /etc/nginx/sites-enabled/{$domain}", true);

        // Usuń domyślną konfigurację jeśli istnieje
        $this->executeCommand("sudo rm -f /etc/nginx/sites-enabled/default", true);

        // Test konfiguracji
        $testResult = $this->executeCommand('sudo nginx -t', true);
        if ($testResult['exit_code'] !== 0) {
            Log::error('Nginx config test failed', [
                'domain' => $domain,
                'output' => $testResult['output'],
            ]);

            return false;
        }

        // Przeładuj Nginx
        $reloadResult = $this->executeCommand('sudo systemctl reload nginx', true);

        return $reloadResult['exit_code'] === 0;
    }

    /**
     * Sprawdza status serwisu.
     */
    public function checkServiceStatus(string $service): bool
    {
        $result = $this->executeCommand("systemctl is-active --quiet {$service} || systemctl is-active --quiet php8.2-{$service}");

        return $result['exit_code'] === 0;
    }

    /**
     * Tworzy katalog na VPS.
     */
    public function createDirectory(string $path, string $owner = 'debian'): bool
    {
        $result = $this->executeCommand("sudo mkdir -p {$path} && sudo chown -R {$owner}:{$owner} {$path}", true);

        return $result['exit_code'] === 0;
    }

    /**
     * Generuje konfigurację Nginx dla domeny.
     */
    public function generateNginxConfig(string $domain, string $rootPath, ?string $phpFpmSocket = null): string
    {
        // Wykryj socket PHP-FPM
        if ($phpFpmSocket === null) {
            $socketResult = $this->executeCommand('ls /var/run/php/php8.2-fpm.sock 2>/dev/null || ls /var/run/php/php-fpm.sock 2>/dev/null || echo "/var/run/php/php8.2-fpm.sock"');
            $phpFpmSocket = trim($socketResult['output']) ?: '/var/run/php/php8.2-fpm.sock';
        }

        $config = <<<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name {$domain} www.{$domain};
    
    root {$rootPath}/public;
    index index.php index.html;
    
    charset utf-8;
    
    # Logowanie
    access_log /var/log/nginx/{$domain}-access.log;
    error_log /var/log/nginx/{$domain}-error.log;
    
    # Maksymalny rozmiar uploadu
    client_max_body_size 100M;
    
    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    
    # Główna lokalizacja
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }
    
    # Obsługa PHP
    location ~ \.php$ {
        fastcgi_pass unix:{$phpFpmSocket};
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        
        # Nagłówki proxy dla sesji i trusted proxies
        fastcgi_param HTTP_X_FORWARDED_FOR \$remote_addr;
        fastcgi_param HTTP_X_FORWARDED_PROTO \$scheme;
        fastcgi_param HTTP_X_FORWARDED_HOST \$http_host;
        fastcgi_param HTTP_X_REAL_IP \$remote_addr;
        
        fastcgi_hide_header X-Powered-By;
        fastcgi_buffer_size 128k;
        fastcgi_buffers 4 256k;
        fastcgi_busy_buffers_size 256k;
        fastcgi_read_timeout 120;
    }
    
    # Blokuj dostęp do plików .ht*
    location ~ /\.ht {
        deny all;
    }
    
    # Blokuj dostęp do wrażliwych plików
    location ~ /\.(env|git|svn) {
        deny all;
    }
    
    # Optymalizacja statycznych zasobów
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }
    
    # Favicon i robots.txt
    location = /favicon.ico {
        access_log off;
        log_not_found off;
    }
    
    location = /robots.txt {
        access_log off;
        log_not_found off;
    }
    
    # Health check
    location = /health {
        access_log off;
        return 200 "OK";
        add_header Content-Type text/plain;
    }
}
NGINX;

        return $config;
    }

    /**
     * Generuje konfigurację Nginx dla statycznego HTML (Next.js static export).
     */
    public function generateStaticNginxConfig(string $domain, string $rootPath): string
    {
        $config = <<<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name {$domain} www.{$domain};
    
    root {$rootPath};
    index index.html;
    
    charset utf-8;
    
    # Logowanie
    access_log /var/log/nginx/{$domain}-access.log;
    error_log /var/log/nginx/{$domain}-error.log;
    
    # Maksymalny rozmiar uploadu
    client_max_body_size 100M;
    
    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    
    # Główna lokalizacja - SPA routing
    location / {
        try_files \$uri \$uri/ /index.html;
    }
    
    # Optymalizacja statycznych zasobów
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot|webp)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }
    
    # Favicon i robots.txt
    location = /favicon.ico {
        access_log off;
        log_not_found off;
    }
    
    location = /robots.txt {
        access_log off;
        log_not_found off;
    }
    
    # Health check
    location = /health {
        access_log off;
        return 200 "OK";
        add_header Content-Type text/plain;
    }
    
    # Blokuj dostęp do plików .ht*
    location ~ /\.ht {
        deny all;
    }
    
    # Blokuj dostęp do wrażliwych plików
    location ~ /\.(env|git|svn) {
        deny all;
    }
}
NGINX;

        return $config;
    }

    /**
     * Generuje konfigurację Nginx dla Next.js SSR (Node.js server).
     */
    public function generateNextJSNginxConfig(string $domain, int $port = 3000): string
    {
        $config = <<<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name {$domain} www.{$domain};
    
    charset utf-8;
    
    # Logowanie
    access_log /var/log/nginx/{$domain}-access.log;
    error_log /var/log/nginx/{$domain}-error.log;
    
    # Maksymalny rozmiar uploadu
    client_max_body_size 100M;
    
    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    
    # Proxy do Next.js
    location / {
        proxy_pass http://localhost:{$port};
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_cache_bypass \$http_upgrade;
        
        # Timeouts
        proxy_connect_timeout 60s;
        proxy_send_timeout 60s;
        proxy_read_timeout 60s;
    }
    
    # Optymalizacja statycznych zasobów (_next/static)
    location /_next/static {
        proxy_pass http://localhost:{$port};
        proxy_cache_valid 200 60m;
        add_header Cache-Control "public, max-age=3600, immutable";
    }
    
    # Favicon i robots.txt
    location = /favicon.ico {
        proxy_pass http://localhost:{$port};
        access_log off;
        log_not_found off;
    }
    
    location = /robots.txt {
        proxy_pass http://localhost:{$port};
        access_log off;
        log_not_found off;
    }
    
    # Health check
    location = /health {
        proxy_pass http://localhost:{$port}/health;
        access_log off;
    }
}
NGINX;

        return $config;
    }

    /**
     * Konfiguruje Node.js aplikację (Next.js SSR) na VPS.
     *
     * @param string $domain Nazwa domeny
     * @param string $appPath Ścieżka do aplikacji (z .next)
     * @param int $port Port dla aplikacji (domyślnie 3000)
     * @return bool
     */
    public function setupNodeJSApp(string $domain, string $appPath, int $port = 3000): bool
    {
        // 1. Sprawdź czy Node.js jest zainstalowany
        $nodeCheck = $this->executeCommand('node --version');
        if ($nodeCheck['exit_code'] !== 0) {
            Log::error('Node.js nie jest zainstalowany na VPS');
            return false;
        }

        // 2. Sprawdź czy PM2 jest zainstalowany
        $pm2Check = $this->executeCommand('pm2 --version');
        if ($pm2Check['exit_code'] !== 0) {
            // Instaluj PM2 globalnie
            $installPM2 = $this->executeCommand('sudo npm install -g pm2', true);
            if ($installPM2['exit_code'] !== 0) {
                Log::error('Nie udało się zainstalować PM2');
                return false;
            }
        }

        // 3. Zatrzymaj istniejącą aplikację (jeśli istnieje)
        $this->executeCommand("pm2 stop {$domain} || true");
        $this->executeCommand("pm2 delete {$domain} || true");

        // 4. Uruchom aplikację przez PM2
        $startCommand = "cd {$appPath} && pm2 start npm --name '{$domain}' -- start";
        $result = $this->executeCommand($startCommand, true);

        if ($result['exit_code'] !== 0) {
            Log::error('Nie udało się uruchomić aplikacji przez PM2', [
                'output' => $result['output'],
            ]);
            return false;
        }

        // 5. Zapisz konfigurację PM2
        $this->executeCommand('pm2 save', true);
        $this->executeCommand('pm2 startup', true);

        // 6. Sprawdź czy aplikacja działa
        sleep(2); // Czekaj na start
        $status = $this->executeCommand("pm2 status {$domain}");

        if (strpos($status['output'], 'online') === false) {
            Log::error('Aplikacja nie działa po starcie');
            return false;
        }

        return true;
    }

    /**
     * Wykonuje health check na endpoint.
     */
    public function healthCheck(string $url, int $timeout = 10): bool
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode === 200 && $response !== false;
    }

    /**
     * Tworzy backup przed deploymentem.
     */
    public function createBackup(string $remotePath, string $backupPath): bool
    {
        $result = $this->executeCommand("sudo cp -r {$remotePath} {$backupPath}", true);

        return $result['exit_code'] === 0;
    }

    /**
     * Przywraca backup (rollback).
     */
    public function restoreBackup(string $backupPath, string $remotePath): bool
    {
        $result = $this->executeCommand("sudo rm -rf {$remotePath} && sudo cp -r {$backupPath} {$remotePath}", true);

        return $result['exit_code'] === 0;
    }

    /**
     * Zero-downtime deployment (symlink swap).
     */
    public function deployWithSymlink(string $domain, string $releasePath, string $currentPath = 'current', string $releasesPath = 'releases'): bool
    {
        $remoteDir = "{$this->wwwRoot}/{$domain}";
        $timestamp = date('Ymd-His');
        $newReleasePath = "{$remoteDir}/{$releasesPath}/{$timestamp}";

        // Utwórz katalog dla nowego release
        if (! $this->createDirectory($newReleasePath)) {
            return false;
        }

        // Skopiuj pliki do nowego release (symlink do aktualnego katalogu)
        // W rzeczywistości pliki powinny być już tam przez uploadFiles

        // Utwórz nowy symlink
        $tempLink = "{$remoteDir}/{$currentPath}.new";
        $this->executeCommand("sudo ln -sfn {$newReleasePath} {$tempLink}", true);
        $this->executeCommand("sudo mv -T {$tempLink} {$remoteDir}/{$currentPath}", true);

        return true;
    }

    /**
     * Instaluje wymagane pakiety na VPS.
     */
    public function installPackages(array $packages): bool
    {
        $packagesList = implode(' ', array_map('escapeshellarg', $packages));
        $result = $this->executeCommand("sudo apt-get update && sudo apt-get install -y {$packagesList}", true);

        return $result['exit_code'] === 0;
    }

    /**
     * Sprawdza czy pakiet jest zainstalowany.
     */
    public function isPackageInstalled(string $package): bool
    {
        $result = $this->executeCommand("which {$package} >/dev/null 2>&1");

        return $result['exit_code'] === 0;
    }
}
