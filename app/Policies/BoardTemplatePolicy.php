<?php

namespace App\Policies;

use App\Models\BoardTemplate;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class BoardTemplatePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any templates.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return true; // All authenticated users can view templates
    }

    /**
     * Determine whether the user can view the template.
     *
     * @param User $user
     * @param BoardTemplate $boardTemplate
     * @return bool
     */
    public function view(User $user, BoardTemplate $boardTemplate): bool
    {
        // Users can view system templates or templates in their organization
        if ($boardTemplate->is_system) {
            return true;
        }

        return $user->organisation_id === $boardTemplate->organisation_id
            || $user->hasRole('admin');
    }

    /**
     * Determine whether the user can create templates.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('manage board templates')
            || $user->hasRole('admin');
    }

    /**
     * Determine whether the user can update the template.
     *
     * @param User $user
     * @param BoardTemplate $boardTemplate
     * @return bool
     */
    public function update(User $user, BoardTemplate $boardTemplate): bool
    {
        // Only admins can update system templates
        if ($boardTemplate->is_system) {
            return $user->hasRole('admin');
        }

        // Organization templates can be updated by org members with permission
        return ($user->organisation_id === $boardTemplate->organisation_id
                && $user->hasPermission('manage board templates'))
            || $user->hasRole('admin');
    }

    /**
     * Determine whether the user can delete the template.
     *
     * @param User $user
     * @param BoardTemplate $boardTemplate
     * @return bool
     */
    public function delete(User $user, BoardTemplate $boardTemplate): bool
    {
        // System templates can't be deleted
        if ($boardTemplate->is_system) {
            return false;
        }

        // Organization templates can be deleted by org members with permission
        return ($user->organisation_id === $boardTemplate->organisation_id
                && $user->hasPermission('manage board templates'))
            || $user->hasRole('admin');
    }

    /**
     * Determine whether the user can duplicate the template.
     *
     * @param User $user
     * @param BoardTemplate $boardTemplate
     * @return bool
     */
    public function duplicate(User $user, BoardTemplate $boardTemplate): bool
    {
        return $user->hasPermission('board.create');

    }
}
