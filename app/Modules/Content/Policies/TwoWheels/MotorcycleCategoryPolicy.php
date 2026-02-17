<?php

namespace App\Modules\Content\Policies\TwoWheels;

use App\Models\User;
use App\Modules\Content\Models\TwoWheels\MotorcycleCategory;
use Illuminate\Auth\Access\HandlesAuthorization;

class MotorcycleCategoryPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_modules::content::models::two::wheels::motorcycle::category');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, MotorcycleCategory $motorcycleCategory): bool
    {
        return $user->can('view_modules::content::models::two::wheels::motorcycle::category');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_modules::content::models::two::wheels::motorcycle::category');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, MotorcycleCategory $motorcycleCategory): bool
    {
        return $user->can('update_modules::content::models::two::wheels::motorcycle::category');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, MotorcycleCategory $motorcycleCategory): bool
    {
        return $user->can('delete_modules::content::models::two::wheels::motorcycle::category');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_modules::content::models::two::wheels::motorcycle::category');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, MotorcycleCategory $motorcycleCategory): bool
    {
        return $user->can('force_delete_modules::content::models::two::wheels::motorcycle::category');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_modules::content::models::two::wheels::motorcycle::category');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, MotorcycleCategory $motorcycleCategory): bool
    {
        return $user->can('restore_modules::content::models::two::wheels::motorcycle::category');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_modules::content::models::two::wheels::motorcycle::category');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, MotorcycleCategory $motorcycleCategory): bool
    {
        return $user->can('replicate_modules::content::models::two::wheels::motorcycle::category');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_modules::content::models::two::wheels::motorcycle::category');
    }
}
