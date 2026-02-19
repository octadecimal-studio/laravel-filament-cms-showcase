<?php

declare(strict_types=1);

namespace App\Filament\Resources\Modules\Content\Models\TwoWheels\RentalConditionResource\Pages;

use App\Filament\Resources\Modules\Content\Models\TwoWheels\RentalConditionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRentalCondition extends EditRecord
{
    protected static string $resource = RentalConditionResource::class;

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
