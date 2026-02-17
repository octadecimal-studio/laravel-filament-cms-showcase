<?php

namespace App\Filament\Resources\Modules\Content\Models\TwoWheels\MotorcycleCategoryResource\Pages;

use App\Filament\Resources\Modules\Content\Models\TwoWheels\MotorcycleCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMotorcycleCategory extends EditRecord
{
    protected static string $resource = MotorcycleCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
