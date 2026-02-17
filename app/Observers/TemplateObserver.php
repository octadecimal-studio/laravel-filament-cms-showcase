<?php

declare(strict_types=1);

namespace App\Observers;

use App\Modules\Content\Models\SiteContent;
use App\Modules\Generator\Models\Template;
use Illuminate\Support\Facades\Log;

/**
 * Observer dla modelu Template.
 * 
 * Obsługuje kaskadowe kasowanie powiązanych treści przy usuwaniu szablonu.
 */
final class TemplateObserver
{
    /**
     * Obsługa przed kasowaniem szablonu.
     * 
     * Kasuje wszystkie powiązane treści (SiteContent) które używają tego szablonu
     * przez Site (template_id lub template_slug) lub bezpośrednio przez slug/directory_path.
     */
    public function deleting(Template $template): void
    {
        $templateSlug = $template->slug;
        $templateDirectoryPath = $template->directory_path;
        
        Log::info('TemplateObserver: Kasowanie powiązanych treści', [
            'template_id' => $template->id,
            'template_slug' => $templateSlug,
            'template_directory_path' => $templateDirectoryPath,
        ]);
        
        // 1. Znajdź wszystkie Site, które używają tego szablonu
        $sites = \App\Models\Site::where('template_id', $template->id)
            ->orWhere('template_slug', $templateSlug)
            ->get();
        
        $siteIds = $sites->pluck('id')->toArray();
        
        // 2. Znajdź wszystkie SiteContent powiązane z tymi Site
        $siteContents = SiteContent::whereIn('site_id', $siteIds)->get();
        
        $deletedCount = 0;
        
        // 3. Kasuj SiteContent
        foreach ($siteContents as $siteContent) {
            $siteContent->delete();
            $deletedCount++;
        }
        
        // 4. Kasuj również SiteContent, które mogą być powiązane bezpośrednio przez slug lub directory_path
        // (jeśli w data jest informacja o template)
        $additionalContents = SiteContent::where(function ($query) use ($templateSlug, $templateDirectoryPath) {
            // Sprawdź czy slug lub directory_path jest w data JSON
            $query->whereJsonContains('data->template_slug', $templateSlug)
                ->orWhereJsonContains('data->template_directory', $templateDirectoryPath)
                ->orWhereJsonContains('data->template', $templateSlug)
                ->orWhereJsonContains('data->template', $templateDirectoryPath);
        })->get();
        
        foreach ($additionalContents as $siteContent) {
            // Sprawdź czy nie został już skasowany
            if (! $siteContent->trashed()) {
                $siteContent->delete();
                $deletedCount++;
            }
        }
        
        // 5. Kasuj również Site, które używają tego szablonu
        foreach ($sites as $site) {
            $site->delete();
        }
        
        Log::info('TemplateObserver: Zakończono kasowanie powiązanych treści', [
            'template_id' => $template->id,
            'deleted_sites_count' => $sites->count(),
            'deleted_site_contents_count' => $deletedCount,
        ]);
    }
}
