<?php

namespace App\Filament\Resources\Modules\Content\Models\TwoWheels\FeatureResource\Pages;

use App\Filament\Resources\Modules\Content\Models\TwoWheels\FeatureResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFeatures extends ListRecords
{
    protected static string $resource = FeatureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
