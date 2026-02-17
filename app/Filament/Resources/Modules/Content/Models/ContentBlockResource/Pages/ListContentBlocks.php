<?php

declare(strict_types=1);

namespace App\Filament\Resources\Modules\Content\Models\ContentBlockResource\Pages;

use App\Filament\Resources\Modules\Content\Models\ContentBlockResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListContentBlocks extends ListRecords
{
    protected static string $resource = ContentBlockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
