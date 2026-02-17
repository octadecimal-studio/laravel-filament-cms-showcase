<?php

namespace App\Filament\Resources\Modules\Generator\Models\TemplateResource\Pages;

use App\Filament\Resources\Modules\Generator\Models\TemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTemplates extends ListRecords
{
    protected static string $resource = TemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
