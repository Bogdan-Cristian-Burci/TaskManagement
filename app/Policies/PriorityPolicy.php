<?php

namespace App\Policies;

use App\Models\Priority;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PriorityPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view priorities') ;
    }

    public function view(User $user, Priority $priority): bool
    {
        return $user->hasPermissionTo('view priorities');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create priorities');
    }

    public function update(User $user, Priority $priority): bool
    {
        return $user->hasPermissionTo('update priorities');
    }

    public function delete(User $user, Priority $priority): bool
    {
        return $user->hasPermissionTo('delete priorities');
    }

    public function restore(User $user, Priority $priority): bool
    {
        return $user->hasPermissionTo('delete priorities');
    }

    public function forceDelete(User $user, Priority $priority): bool
    {
        return $user->hasPermissionTo('delete priorities');
    }
}
