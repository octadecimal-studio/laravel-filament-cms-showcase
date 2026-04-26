<?php

namespace App\Filament\Resources\Modules\Content\Models\TwoWheels\RentalConditionResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\Modules\Content\Models\TwoWheels\RentalConditionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRentalConditions extends ListRecords
{
    protected static string $resource = RentalConditionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
