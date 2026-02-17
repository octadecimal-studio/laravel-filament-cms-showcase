<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Modules\Deploy\Models\Deployment;
use Livewire\Component;

/**
 * Komponent Livewire do wyświetlania logów deploymentu w real-time.
 */
class DeploymentLogs extends Component
{
    public string $deploymentId;

    public ?Deployment $deployment = null;

    public function mount(string $deploymentId): void
    {
        $this->deploymentId = $deploymentId;
        $this->loadDeployment();
    }

    /**
     * Ładuje deployment z bazy danych.
     */
    public function loadDeployment(): void
    {
        $this->deployment = Deployment::find($this->deploymentId);
    }

    /**
     * Odświeża logi (używane przez wire:poll).
     */
    public function refresh(): void
    {
        $this->loadDeployment();
    }

    public function render()
    {
        return view('livewire.deployment-logs', [
            'logs' => $this->deployment?->logs ?? [],
            'status' => $this->deployment?->status ?? 'unknown',
        ]);
    }
}
