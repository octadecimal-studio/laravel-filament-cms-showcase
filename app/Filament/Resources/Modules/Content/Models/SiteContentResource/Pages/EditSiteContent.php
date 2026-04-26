<?php

declare(strict_types=1);

namespace App\Filament\Resources\Modules\Content\Models\SiteContentResource\Pages;

use Filament\Actions\ViewAction;
use Filament\Actions\DeleteAction;
use App\Modules\Content\Models\ContentBlock;
use App\Modules\Content\Services\ContentBlockValidator;
use Illuminate\Validation\ValidationException;
use App\Filament\Resources\Modules\Content\Models\SiteContentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSiteContent extends EditRecord
{
    protected static string $resource = SiteContentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * Mutate form data before save - przygotuj dane.
     */
    protected function mutateFormDataBeforeSave(array $data): array
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
            $contentBlock = ContentBlock::find($data['content_block_id']);
            if ($contentBlock && $contentBlock->schema) {
                try {
                    $validator = app(ContentBlockValidator::class);
                    $validated = $validator->validate($data, $contentBlock);
                    $data = array_merge($data, $validated);
                } catch (ValidationException $e) {
                    // Błędy walidacji zostaną wyświetlone przez Filament
                    throw $e;
                }
            }
        }

        return $data;
    }
}
