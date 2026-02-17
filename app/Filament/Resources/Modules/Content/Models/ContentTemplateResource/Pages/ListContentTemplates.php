<?php

declare(strict_types=1);

namespace App\Filament\Resources\Modules\Content\Models\ContentTemplateResource\Pages;

use App\Filament\Resources\Modules\Content\Models\ContentTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListContentTemplates extends ListRecords
{
    protected static string $resource = ContentTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
