<?php

namespace App\Filament\Resources\Modules\Content\Models\TwoWheels\ProcessStepResource\Pages;

use App\Filament\Resources\Modules\Content\Models\TwoWheels\ProcessStepResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProcessStep extends EditRecord
{
    protected static string $resource = ProcessStepResource::class;

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
