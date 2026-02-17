<?php

declare(strict_types=1);

namespace App\Modules\Generator\Services;

use App\Jobs\ConfigureDNSJob;
use App\Jobs\DeployProjectJob;
use App\Jobs\RequestSSLJob;
use App\Models\Customer;
use App\Models\Site;
use App\Modules\Content\Models\ContentBlock;
use App\Modules\Content\Models\SiteContent;
use App\Modules\Deploy\Models\Deployment;
use App\Modules\Deploy\Models\Domain;
use App\Modules\Generator\Models\Template;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Serwis do wdrożenia szablonu jako działającej strony z CMS.
 *
 * Workflow:
 * 1. Template -> Site (tworzy rekord Site)
 * 2. Site -> Deployment (tworzy Domain i Deployment)
 * 3. Deployment -> Jobs (DNS, Upload, SSL)
 * 4. Opcjonalnie: Fill Data (wypełnia przykładowymi danymi z ContentBlocks)
 * 5. Opcjonalnie: Publish (ustawia status na 'live')
 */
final class TemplateDeploymentService
{
    public function __construct(
        private TemplateAnalyzerService $analyzerService
    ) {
    }

    /**
     * Wdróż szablon jako działającą stronę.
     *
     * @param  Template  $template  Szablon do wdrożenia
     * @param  array<string, mixed>  $options  Opcje wdrożenia
     * @return array{site: Site, deployment: Deployment, domain: Domain}
     */
    public function deployTemplate(
        Template $template,
        array $options = []
    ): array {
        $customerId = $options['customer_id'] ?? $this->getDefaultCustomerId();
        $subdomain = $options['subdomain'] ?? $this->generateSubdomain($template);
        $domain = $options['domain'] ?? config('app.domain', 'octadecimal.studio');
        $fullDomain = "{$subdomain}.{$domain}";
        $fillWithExampleData = $options['fill_with_example_data'] ?? false;
        $publish = $options['publish'] ?? false;

        DB::beginTransaction();

        try {
            // 1. Utwórz Site
            $site = Site::create([
                'customer_id' => $customerId,
                'template_id' => $template->id,
                'template_slug' => $template->slug,
                'name' => $options['site_name'] ?? $template->name,
                'slug' => $options['site_slug'] ?? Str::slug($template->name),
                'status' => $publish ? 'live' : 'development',
                'production_url' => $publish ? "https://{$fullDomain}" : null,
            ]);

            Log::info('TemplateDeploymentService: Site created', [
                'site_id' => $site->id,
                'template_id' => $template->id,
            ]);

            // 2. Utwórz Domain
            $domainModel = Domain::firstOrCreate(
                ['domain' => $fullDomain],
                [
                    'tenant_id' => $template->tenant_id,
                    'subdomain' => $subdomain,
                    'dns_status' => Domain::DNS_STATUS_PENDING,
                    'ssl_status' => Domain::SSL_STATUS_PENDING,
                    'vps_ip' => config('vps.ip', '203.0.113.10'),
                ]
            );

            Log::info('TemplateDeploymentService: Domain created', [
                'domain_id' => $domainModel->id,
                'domain' => $fullDomain,
            ]);

            // 3. Utwórz Deployment
            $deployment = Deployment::create([
                'tenant_id' => $template->tenant_id,
                'domain_id' => $domainModel->id,
                'status' => Deployment::STATUS_PENDING,
                'version' => date('Ymd-His'),
                'metadata' => [
                    'template_id' => $template->id,
                    'template_path' => $template->directory_path, // Ścieżka względna (templates/...)
                    'template_full_path' => $template->getFullPath(), // Pełna ścieżka absolutna
                    'site_id' => $site->id,
                    'subdomain' => $subdomain,
                    'domain' => $domain,
                ],
            ]);

            Log::info('TemplateDeploymentService: Deployment created', [
                'deployment_id' => $deployment->id,
                'site_id' => $site->id,
            ]);

            // 4. Uruchom joby deploymentu
            $this->dispatchDeploymentJobs($deployment, $domainModel, $fullDomain, $template);

            // 5. Opcjonalnie: wypełnij danymi przykładowymi
            if ($fillWithExampleData) {
                $this->fillSiteWithExampleData($site, $template);
            }

            // 6. Opcjonalnie: opublikuj
            if ($publish) {
                $site->update([
                    'status' => 'live',
                    'published_at' => now(),
                ]);
            }

            DB::commit();

            Log::info('TemplateDeploymentService: Deployment completed', [
                'site_id' => $site->id,
                'deployment_id' => $deployment->id,
                'domain' => $fullDomain,
            ]);

            return [
                'site' => $site,
                'deployment' => $deployment,
                'domain' => $domainModel,
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('TemplateDeploymentService: Deployment failed', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Uruchom joby deploymentu (DNS, Upload, SSL).
     */
    private function dispatchDeploymentJobs(
        Deployment $deployment,
        Domain $domain,
        string $fullDomain,
        Template $template
    ): void {
        // Użyj metody getFullPath() z modelu Template
        $templatePath = $template->getFullPath();

        if (! is_dir($templatePath)) {
            throw new \RuntimeException("Template path does not exist: {$templatePath}");
        }

        // 1. DNS
        ConfigureDNSJob::dispatch(
            $domain->id,
            $domain->domain,
            explode('.', $fullDomain)[0],
            config('vps.ip', '203.0.113.10')
        );

        // 2. Deploy
        DeployProjectJob::dispatch(
            $deployment->id,
            $fullDomain,
            $templatePath,
            $deployment->version
        );

        // 3. SSL
        RequestSSLJob::dispatch(
            $domain->id,
            $fullDomain
        );
    }

    /**
     * Wypełnij Site przykładowymi danymi z ContentBlocks.
     */
    private function fillSiteWithExampleData(Site $site, Template $template): void
    {
        // Pobierz ContentBlocks powiązane z tym szablonem
        $contentBlocks = ContentBlock::where('tenant_id', $template->tenant_id)
            ->where('is_active', true)
            ->get();

        if ($contentBlocks->isEmpty()) {
            Log::warning('TemplateDeploymentService: No content blocks found for template', [
                'template_id' => $template->id,
            ]);

            return;
        }

        $order = 0;

        foreach ($contentBlocks as $block) {
            SiteContent::create([
                'tenant_id' => $template->tenant_id,
                'site_id' => $site->id,
                'content_block_id' => $block->id,
                'type' => 'section',
                'title' => $block->name,
                'slug' => Str::slug($block->name),
                'description' => $block->description,
                'data' => $block->default_data ?? [],
                'status' => 'published',
                'published_at' => now(),
                'order' => $order++,
                'is_current_version' => true,
                'version' => 1,
            ]);
        }

        // Zaktualizuj licznik stron
        $site->update(['pages_count' => $contentBlocks->count()]);

        Log::info('TemplateDeploymentService: Site filled with example data', [
            'site_id' => $site->id,
            'content_blocks_count' => $contentBlocks->count(),
        ]);
    }

    /**
     * Pobierz domyślnego klienta (lub utwórz).
     */
    private function getDefaultCustomerId(): string
    {
        $customer = Customer::first();

        if (! $customer) {
            // Utwórz domyślnego klienta
            $customer = Customer::create([
                'name' => 'Default Client',
                'email' => 'client@example.com',
                'status' => 'active',
            ]);
        }

        return $customer->id;
    }

    /**
     * Wygeneruj subdomenę z nazwy szablonu.
     */
    private function generateSubdomain(Template $template): string
    {
        return Str::slug($template->name);
    }
}
