<?php

namespace App\Modules\Content\Policies\TwoWheels;

use App\Models\User;
use App\Modules\Content\Models\TwoWheels\Motorcycle;
use Illuminate\Auth\Access\HandlesAuthorization;

class MotorcyclePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_modules::content::models::two::wheels::motorcycle');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Motorcycle $motorcycle): bool
    {
        return $user->can('view_modules::content::models::two::wheels::motorcycle');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_modules::content::models::two::wheels::motorcycle');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Motorcycle $motorcycle): bool
    {
        return $user->can('update_modules::content::models::two::wheels::motorcycle');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Motorcycle $motorcycle): bool
    {
        return $user->can('delete_modules::content::models::two::wheels::motorcycle');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_modules::content::models::two::wheels::motorcycle');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, Motorcycle $motorcycle): bool
    {
        return $user->can('force_delete_modules::content::models::two::wheels::motorcycle');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_modules::content::models::two::wheels::motorcycle');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, Motorcycle $motorcycle): bool
    {
        return $user->can('restore_modules::content::models::two::wheels::motorcycle');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_modules::content::models::two::wheels::motorcycle');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, Motorcycle $motorcycle): bool
    {
        return $user->can('replicate_modules::content::models::two::wheels::motorcycle');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_modules::content::models::two::wheels::motorcycle');
    }
}
