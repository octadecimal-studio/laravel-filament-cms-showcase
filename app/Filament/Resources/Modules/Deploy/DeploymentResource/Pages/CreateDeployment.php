<?php

declare(strict_types=1);

namespace App\Filament\Resources\Modules\Deploy\DeploymentResource\Pages;

use App\Filament\Resources\Modules\Deploy\DeploymentResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateDeployment extends CreateRecord
{
    protected static string $resource = DeploymentResource::class;
}
