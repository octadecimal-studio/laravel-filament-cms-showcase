<?php

declare(strict_types=1);

namespace App\Observers;

use App\Modules\Content\Models\SiteContent;
use App\Modules\Generator\Models\Template;
use App\Services\TemplateRevalidationService;
use Illuminate\Support\Facades\Log;

/**
 * Observer for SiteContent model.
 *
 * Triggers template revalidation when SiteContent with ContentBlock is published or updated.
 */
class SiteContentObserver
{
    public function __construct(
        private TemplateRevalidationService $revalidation
    ) {
    }

    /**
     * Handle the SiteContent "updated" event.
     */
    public function updated(SiteContent $siteContent): void
    {
        // Tylko jeśli status zmienił się na 'published' lub dane zostały zmienione
        if ($siteContent->wasChanged('status') && $siteContent->status === 'published') {
            $this->triggerRevalidation($siteContent);
        } elseif ($siteContent->wasChanged('data') && $siteContent->status === 'published') {
            $this->triggerRevalidation($siteContent);
        }
    }

    /**
     * Handle the SiteContent "created" event.
     */
    public function created(SiteContent $siteContent): void
    {
        // Tylko jeśli od razu opublikowane
        if ($siteContent->status === 'published') {
            $this->triggerRevalidation($siteContent);
        }
    }

    /**
     * Trigger revalidation for affected templates.
     */
    private function triggerRevalidation(SiteContent $siteContent): void
    {
        // Tylko jeśli SiteContent ma przypisany ContentBlock
        if (! $siteContent->content_block_id) {
            return;
        }

        // Znajdź wszystkie szablony dla tego tenanta z webhookami
        $templates = Template::where('tenant_id', $siteContent->tenant_id)
            ->whereNotNull('webhook_url')
            ->pluck('slug')
            ->toArray();

        if (empty($templates)) {
            Log::debug('No templates to revalidate for SiteContent', [
                'site_content_id' => $siteContent->id,
                'tenant_id' => $siteContent->tenant_id,
            ]);

            return;
        }

        // Przygotuj tagi dla rewalidacji
        $tags = ['content', 'site-content'];
        
        if ($siteContent->contentBlock) {
            $tags[] = $siteContent->contentBlock->category ?? 'general';
            $tags[] = $siteContent->contentBlock->slug;
        }

        if ($siteContent->slug) {
            $tags[] = "page:{$siteContent->slug}";
        }

        // Dispatch revalidation for each template (async, after response)
        foreach ($templates as $templateSlug) {
            dispatch(function () use ($templateSlug, $tags, $siteContent) {
                $this->revalidation->revalidate(
                    $templateSlug,
                    $tags,
                    $siteContent->slug ? "/{$siteContent->slug}" : null,
                    $siteContent->tenant_id
                );
            })->afterResponse();
        }

        Log::info('Revalidation triggered for SiteContent', [
            'site_content_id' => $siteContent->id,
            'content_block_id' => $siteContent->content_block_id,
            'tenant_id' => $siteContent->tenant_id,
            'templates' => $templates,
            'tags' => $tags,
        ]);
    }
}
