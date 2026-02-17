<?php

declare(strict_types=1);

namespace App\Filament\Resources\DeploymentResource\Pages;

use App\Filament\Resources\DeploymentResource;
use Filament\Resources\Pages\ViewRecord;

class ViewDeployment extends ViewRecord
{
    protected static string $resource = DeploymentResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * Auto-odświeżanie dla aktywnych deploymentów.
     */
    public function getPollingInterval(): ?string
    {
        return $this->record->isInProgress() ? '5s' : null;
    }
}
