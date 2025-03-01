<?php

namespace App\Policies;

use App\Models\Organisation;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class OrganisationPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Organisation $organisation): bool
    {
        return $user->organisations->contains($organisation->id) ||
            $user->hasRole(['admin', 'super-admin']) ||
            $user->hasPermissionTo('view-organisations');
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Organisation $organisation): bool
    {
        return $user->id === $organisation->owner_id ||
            $user->id === $organisation->created_by ||
            $user->hasRole(['admin', 'super-admin']);
    }

    public function delete(User $user, Organisation $organisation): bool
    {
        return $user->id === $organisation->owner_id ||
            $user->hasRole(['admin', 'super-admin']);
    }

    public function restore(User $user, Organisation $organisation): bool
    {
        return $user->hasRole(['admin', 'super-admin']);
    }

    public function forceDelete(User $user, Organisation $organisation): bool
    {
        return $user->hasRole(['admin', 'super-admin']);
    }
}
