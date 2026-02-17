<?php

declare(strict_types=1);

namespace App\Filament\Resources\Modules\Content\Models\ContentTemplateResource\Pages;

use App\Filament\Resources\Modules\Content\Models\ContentTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewContentTemplate extends ViewRecord
{
    protected static string $resource = ContentTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
