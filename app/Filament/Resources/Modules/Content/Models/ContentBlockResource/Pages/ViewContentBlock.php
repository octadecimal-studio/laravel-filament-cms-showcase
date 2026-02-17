<?php

declare(strict_types=1);

namespace App\Filament\Resources\Modules\Content\Models\ContentBlockResource\Pages;

use App\Filament\Resources\Modules\Content\Models\ContentBlockResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewContentBlock extends ViewRecord
{
    protected static string $resource = ContentBlockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
