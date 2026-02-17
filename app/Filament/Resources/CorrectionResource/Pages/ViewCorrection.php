<?php

declare(strict_types=1);

namespace App\Filament\Resources\CorrectionResource\Pages;

use App\Filament\Resources\CorrectionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCorrection extends ViewRecord
{
    protected static string $resource = CorrectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
