<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Policy dla deploymentów (Deployment).
 *
 * Klient NIE widzi deploymentów (DevOps).
 */
class DeploymentPolicy extends BasePolicy
{
    protected string $permissionPrefix = 'deployments';

    /**
     * Klient nie widzi deploymentów.
     */
    public function viewAny(User $user): bool
    {
        if ($user->hasRole('client')) {
            return false;
        }

        return parent::viewAny($user);
    }

    /**
     * Nikt nie tworzy deploymentów ręcznie.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Deploymenty są read-only.
     */
    public function update(User $user, $model): bool
    {
        return false;
    }

    /**
     * Deploymenty są read-only.
     */
    public function delete(User $user, $model): bool
    {
        return false;
    }
}
