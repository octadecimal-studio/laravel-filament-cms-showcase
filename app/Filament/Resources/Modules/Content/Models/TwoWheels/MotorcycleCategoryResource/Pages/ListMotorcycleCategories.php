<?php

namespace App\Filament\Resources\Modules\Content\Models\TwoWheels\MotorcycleCategoryResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\Modules\Content\Models\TwoWheels\MotorcycleCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMotorcycleCategories extends ListRecords
{
    protected static string $resource = MotorcycleCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
