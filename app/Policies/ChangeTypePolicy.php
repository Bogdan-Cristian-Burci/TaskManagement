<?php

namespace App\Policies;

use App\Models\ChangeType;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ChangeTypePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user):bool
    {
        return true;
    }

    public function view(User $user, ChangeType $changeType):bool
    {
        return true;
    }

    public function create(User $user):bool
    {
        return $user->hasRole('admin') || $user->hasPermissionTo('manage task settings');
    }

    public function update(User $user, ChangeType $changeType):bool
    {
        return $user->hasRole('admin') || $user->hasPermissionTo('manage task settings');
    }

    public function delete(User $user, ChangeType $changeType):bool
    {
        return $user->hasRole('admin') || $user->hasPermissionTo('manage task settings');
    }
}
