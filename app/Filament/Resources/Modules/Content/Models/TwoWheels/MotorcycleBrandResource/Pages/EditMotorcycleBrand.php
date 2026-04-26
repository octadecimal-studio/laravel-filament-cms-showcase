<?php

namespace App\Filament\Resources\Modules\Content\Models\TwoWheels\MotorcycleBrandResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\Modules\Content\Models\TwoWheels\MotorcycleBrandResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMotorcycleBrand extends EditRecord
{
    protected static string $resource = MotorcycleBrandResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
