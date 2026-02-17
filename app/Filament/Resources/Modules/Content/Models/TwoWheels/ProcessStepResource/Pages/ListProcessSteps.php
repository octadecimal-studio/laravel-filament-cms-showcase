<?php

namespace App\Filament\Resources\Modules\Content\Models\TwoWheels\ProcessStepResource\Pages;

use App\Filament\Resources\Modules\Content\Models\TwoWheels\ProcessStepResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProcessSteps extends ListRecords
{
    protected static string $resource = ProcessStepResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
