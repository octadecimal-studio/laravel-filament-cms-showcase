<?php

namespace App\Filament\Resources\Modules\Content\Models\TwoWheels\MotorcycleResource\Pages;

use App\Filament\Resources\Modules\Content\Models\TwoWheels\MotorcycleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMotorcycles extends ListRecords
{
    protected static string $resource = MotorcycleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
