<?php

namespace App\Filament\Resources\Modules\Content\Models\TwoWheels\MotorcycleBrandResource\Pages;

use App\Filament\Resources\Modules\Content\Models\TwoWheels\MotorcycleBrandResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMotorcycleBrand extends EditRecord
{
    protected static string $resource = MotorcycleBrandResource::class;

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
