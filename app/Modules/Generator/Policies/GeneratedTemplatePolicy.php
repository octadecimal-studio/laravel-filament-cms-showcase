<?php

namespace App\Modules\Generator\Policies;

use App\Models\User;
use App\Modules\Generator\Models\GeneratedTemplate;
use Illuminate\Auth\Access\HandlesAuthorization;

class GeneratedTemplatePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_modules::generator::models::generated::template');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, GeneratedTemplate $generatedTemplate): bool
    {
        return $user->can('view_modules::generator::models::generated::template');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_modules::generator::models::generated::template');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, GeneratedTemplate $generatedTemplate): bool
    {
        return $user->can('update_modules::generator::models::generated::template');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, GeneratedTemplate $generatedTemplate): bool
    {
        return $user->can('delete_modules::generator::models::generated::template');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_modules::generator::models::generated::template');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, GeneratedTemplate $generatedTemplate): bool
    {
        return $user->can('force_delete_modules::generator::models::generated::template');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_modules::generator::models::generated::template');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, GeneratedTemplate $generatedTemplate): bool
    {
        return $user->can('restore_modules::generator::models::generated::template');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_modules::generator::models::generated::template');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, GeneratedTemplate $generatedTemplate): bool
    {
        return $user->can('replicate_modules::generator::models::generated::template');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_modules::generator::models::generated::template');
    }
}
