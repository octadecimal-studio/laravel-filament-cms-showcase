<?php

declare(strict_types=1);

namespace App\Filament\Resources\Modules\Content\Models\TwoWheels\PricingNoteResource\Pages;

use App\Filament\Resources\Modules\Content\Models\TwoWheels\PricingNoteResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPricingNote extends EditRecord
{
    protected static string $resource = PricingNoteResource::class;

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
