<?php

declare(strict_types=1);

namespace App\Filament\Resources\CorrectionResource\Pages;

use App\Filament\Resources\CorrectionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCorrection extends EditRecord
{
    protected static string $resource = CorrectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
