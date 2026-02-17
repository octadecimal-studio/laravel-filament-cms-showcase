<?php

namespace App\Filament\Resources\Modules\Generator\Models\TemplateResource\Pages;

use App\Filament\Resources\Modules\Generator\Models\TemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewTemplate extends ViewRecord
{
    protected static string $resource = TemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
