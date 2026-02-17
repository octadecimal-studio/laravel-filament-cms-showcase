<?php

declare(strict_types=1);

namespace App\Filament\Resources\Modules\Content\Models\ContentTemplateResource\Pages;

use App\Filament\Resources\Modules\Content\Models\ContentTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditContentTemplate extends EditRecord
{
    protected static string $resource = ContentTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
