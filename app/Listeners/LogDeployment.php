<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\DeploymentCompleted;
use App\Events\DeploymentFailed;
use App\Events\DeploymentStarted;
use Illuminate\Support\Facades\Log;

/**
 * Listener do logowania eventów deploymentu.
 */
class LogDeployment
{
    /**
     * Obsłuż event DeploymentStarted.
     */
    public function handleStarted(DeploymentStarted $event): void
    {
        Log::info('Deployment started', [
            'deployment_id' => $event->deployment->id,
            'domain' => $event->deployment->domain?->domain,
            'version' => $event->deployment->version,
        ]);
    }

    /**
     * Obsłuż event DeploymentCompleted.
     */
    public function handleCompleted(DeploymentCompleted $event): void
    {
        $duration = $event->deployment->started_at && $event->deployment->completed_at
            ? $event->deployment->started_at->diffInSeconds($event->deployment->completed_at)
            : null;

        Log::info('Deployment completed', [
            'deployment_id' => $event->deployment->id,
            'domain' => $event->deployment->domain?->domain,
            'version' => $event->deployment->version,
            'duration_seconds' => $duration,
        ]);
    }

    /**
     * Obsłuż event DeploymentFailed.
     */
    public function handleFailed(DeploymentFailed $event): void
    {
        Log::error('Deployment failed', [
            'deployment_id' => $event->deployment->id,
            'domain' => $event->deployment->domain?->domain,
            'version' => $event->deployment->version,
            'error' => $event->exception->getMessage(),
            'trace' => $event->exception->getTraceAsString(),
        ]);
    }
}
