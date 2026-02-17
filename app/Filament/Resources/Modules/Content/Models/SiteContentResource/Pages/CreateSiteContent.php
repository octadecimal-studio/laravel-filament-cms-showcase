<?php

declare(strict_types=1);

namespace App\Filament\Resources\Modules\Content\Models\SiteContentResource\Pages;

use App\Filament\Resources\Modules\Content\Models\SiteContentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSiteContent extends CreateRecord
{
    protected static string $resource = SiteContentResource::class;

    /**
     * Mutate form data before create - przygotuj dane.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Jeśli wybrano ContentBlock, upewnij się że type jest ustawione
        if (isset($data['content_block_id']) && !isset($data['type'])) {
            $data['type'] = 'block';
        }

        // Upewnij się że data jest tablicą
        if (isset($data['data']) && !is_array($data['data'])) {
            $data['data'] = [];
        }

        // Waliduj dane zgodnie z schema ContentBlock (jeśli wybrano)
        if (isset($data['content_block_id']) && isset($data['data'])) {
            $contentBlock = \App\Modules\Content\Models\ContentBlock::find($data['content_block_id']);
            if ($contentBlock && $contentBlock->schema) {
                try {
                    $validator = app(\App\Modules\Content\Services\ContentBlockValidator::class);
                    $validated = $validator->validate($data, $contentBlock);
                    $data = array_merge($data, $validated);
                } catch (\Illuminate\Validation\ValidationException $e) {
                    // Błędy walidacji zostaną wyświetlone przez Filament
                    throw $e;
                }
            }
        }

        return $data;
    }
}
