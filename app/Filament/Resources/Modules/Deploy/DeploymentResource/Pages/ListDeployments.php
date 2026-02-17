<?php

declare(strict_types=1);

namespace App\Filament\Resources\Modules\Deploy\DeploymentResource\Pages;

use App\Filament\Resources\Modules\Deploy\DeploymentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDeployments extends ListRecords
{
    protected static string $resource = DeploymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
