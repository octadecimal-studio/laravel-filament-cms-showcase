<?php

declare(strict_types=1);

namespace App\Filament\Resources\CorrectionResource\Pages;

use App\Filament\Resources\CorrectionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCorrection extends CreateRecord
{
    protected static string $resource = CorrectionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['reported_at'] = $data['reported_at'] ?? now();
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
