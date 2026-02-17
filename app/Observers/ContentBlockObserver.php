<?php

namespace App\Observers;

use App\Modules\Content\Models\ContentBlock;
use App\Modules\Generator\Models\Template;
use App\Services\TemplateRevalidationService;
use Illuminate\Support\Facades\Log;

/**
 * Observer for ContentBlock model.
 *
 * Triggers template revalidation when content blocks are created, updated, or deleted.
 */
class ContentBlockObserver
{
    public function __construct(
        private TemplateRevalidationService $revalidation
    ) {
    }

    /**
     * Handle the ContentBlock "created" event.
     */
    public function created(ContentBlock $contentBlock): void
    {
        $this->triggerRevalidation($contentBlock, ['content', $contentBlock->section ?? 'content', $contentBlock->type ?? 'content']);
    }

    /**
     * Handle the ContentBlock "updated" event.
     */
    public function updated(ContentBlock $contentBlock): void
    {
        $this->triggerRevalidation($contentBlock, ['content', $contentBlock->section ?? 'content', $contentBlock->type ?? 'content']);
    }

    /**
     * Handle the ContentBlock "deleted" event.
     */
    public function deleted(ContentBlock $contentBlock): void
    {
        $this->triggerRevalidation($contentBlock, ['content', $contentBlock->section ?? 'content']);
    }

    /**
     * Trigger revalidation for affected templates.
     *
     * @param ContentBlock $block
     * @param array<string> $tags
     */
    private function triggerRevalidation(ContentBlock $block, array $tags): void
    {
        // ContentBlock doesn't have direct relationship to templates
        // Revalidate all templates for this tenant that have webhooks configured
        // This ensures all deployed templates get updated when content changes
        $templates = Template::where('tenant_id', $block->tenant_id)
            ->whereNotNull('webhook_url')
            ->pluck('slug')
            ->toArray();

        if (empty($templates)) {
            Log::debug('No templates to revalidate', [
                'content_block_id' => $block->id,
                'tenant_id' => $block->tenant_id,
            ]);

            return;
        }

        // Dispatch revalidation for each template (async, after response)
        foreach ($templates as $templateSlug) {
            dispatch(function () use ($templateSlug, $tags, $block) {
                // Pass tenant_id to ensure multi-tenancy security
                $this->revalidation->revalidate($templateSlug, $tags, null, $block->tenant_id);
            })->afterResponse();
        }

        Log::info('Revalidation triggered', [
            'content_block_id' => $block->id,
            'tenant_id' => $block->tenant_id,
            'templates' => $templates,
            'tags' => $tags,
        ]);
    }
}
