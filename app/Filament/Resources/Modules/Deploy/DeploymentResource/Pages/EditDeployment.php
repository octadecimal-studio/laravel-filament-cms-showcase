<?php

declare(strict_types=1);

namespace App\Filament\Resources\Modules\Deploy\DeploymentResource\Pages;

use App\Filament\Resources\Modules\Deploy\DeploymentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDeployment extends EditRecord
{
    protected static string $resource = DeploymentResource::class;

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
