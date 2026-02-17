<?php

namespace App\Filament\Resources\Modules\Content\Models\TwoWheels\MotorcycleCategoryResource\Pages;

use App\Filament\Resources\Modules\Content\Models\TwoWheels\MotorcycleCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMotorcycleCategories extends ListRecords
{
    protected static string $resource = MotorcycleCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
