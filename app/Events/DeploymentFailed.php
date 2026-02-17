<?php

declare(strict_types=1);

namespace App\Events;

use App\Modules\Deploy\Models\Deployment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event wywoływany gdy deployment zakończył się niepowodzeniem.
 */
class DeploymentFailed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Utwórz nowy event.
     */
    public function __construct(
        public Deployment $deployment,
        public \Throwable $exception
    ) {
    }
}
