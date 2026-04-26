<?php

namespace App\Filament\Resources\Modules\Content\Models\TwoWheels\TestimonialResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\Modules\Content\Models\TwoWheels\TestimonialResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTestimonial extends EditRecord
{
    protected static string $resource = TestimonialResource::class;

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
