<?php

namespace App\Filament\Resources\Modules\Content\Models\TwoWheels\MotorcycleBrandResource\Pages;

use App\Filament\Resources\Modules\Content\Models\TwoWheels\MotorcycleBrandResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMotorcycleBrands extends ListRecords
{
    protected static string $resource = MotorcycleBrandResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
