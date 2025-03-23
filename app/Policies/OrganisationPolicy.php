<?php

namespace App\Policies;

use App\Models\Organisation;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class OrganisationPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any organisations.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        // Any authenticated user can view organisations they belong to
        return true;
    }

    /**
     * Determine whether the user can view the organisation.
     *
     * @param User $user
     * @param Organisation $organisation
     * @return bool
     */
    public function view(User $user, Organisation $organisation): bool
    {
        // User can view if they are a member, or they have special permissions
        return $organisation->hasMember($user) ||
            $user->hasPermission('organisation.view');
    }

    /**
     * Determine whether the user can create organisations.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        // Check if the user has reached their maximum number of organisations
        $maxOrganisations = config('app.max_organisations_per_user', 1);
        $currentCount = $user->organisations()->count();

        // Allow creation if under limit or if user has admin/super-admin role
        return $currentCount < $maxOrganisations ||
            $user->hasPermission('organisation.create');
    }

    /**
     * Determine whether the user can update the organisation.
     *
     * @param User $user
     * @param Organisation $organisation
     * @return bool
     */
    public function update(User $user, Organisation $organisation): bool
    {
        // User can update if they are the owner or creator, or have admin role in the org,
        // or have global admin roles
        return
            $user->id === $organisation->created_by ||
            $user->hasPermission('organisation.update');
    }

    /**
     * Determine whether the user can delete the organisation.
     *
     * @param User $user
     * @param Organisation $organisation
     * @return bool
     */
    public function delete(User $user, Organisation $organisation): bool
    {
        // Only the owner or global admins can delete an organisation
        return $user->hasPermission('organisation.delete');
    }

    /**
     * Determine whether the user can restore the organisation.
     *
     * @param User $user
     * @param Organisation $organisation
     * @return bool
     */
    public function restore(User $user, Organisation $organisation): bool
    {
        // Only global admins can restore organisations
        return $user->hasPermission('organisation.restore');
    }

    /**
     * Determine whether the user can permanently delete the organisation.
     *
     * @param User $user
     * @param Organisation $organisation
     * @return bool
     */
    public function forceDelete(User $user, Organisation $organisation): bool
    {
        return $user->hasPermission('organisation.forceDelete');
    }

    /**
     * Determine whether the user can manage members of the organisation.
     *
     * @param User $user
     * @param Organisation $organisation
     * @return bool
     */
    public function manageMembers(User $user, Organisation $organisation): bool
    {
        return $user->hasPermission('manage-organisations');
    }

    /**
     * Determine whether the user can change the owner of the organisation.
     *
     * @param User $user
     * @param Organisation $organisation
     * @return bool
     */
    public function changeOwner(User $user, Organisation $organisation): bool
    {
        return $user->hasPermission('organisation.update');
    }
}
