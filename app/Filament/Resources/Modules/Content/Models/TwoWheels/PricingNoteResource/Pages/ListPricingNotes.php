<?php

namespace App\Filament\Resources\Modules\Content\Models\TwoWheels\PricingNoteResource\Pages;

use App\Filament\Resources\Modules\Content\Models\TwoWheels\PricingNoteResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPricingNotes extends ListRecords
{
    protected static string $resource = PricingNoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
