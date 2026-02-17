<?php

declare(strict_types=1);

namespace App\Filament\Resources\Modules\Content\Models\MediaResource\Pages;

use App\Filament\Resources\Modules\Content\Models\MediaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMedia extends ListRecords
{
    protected static string $resource = MediaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
