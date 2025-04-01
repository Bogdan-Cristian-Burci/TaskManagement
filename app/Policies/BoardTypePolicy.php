<?php

namespace App\Policies;

use App\Models\BoardType;
use App\Models\User;
use App\Services\OrganizationContext;
use Illuminate\Auth\Access\HandlesAuthorization;

class BoardTypePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
         return $user->hasPermission('board.viewAny');
    }

    public function view(User $user, BoardType $boardType): bool
    {
        if (!$user->hasPermission('board.view')) {
            return false;
        }

        // Load template if not already loaded
        if (!$boardType->relationLoaded('template')) {
            $boardType->load('template');
        }

        // If the template doesn't exist, deny access
        if (!$boardType->template) {
            \Log::warning('BoardType accessed with missing template', [
                'board_type_id' => $boardType->id,
                'user_id' => $user->id
            ]);
            return false;
        }

        // Get current user's organization ID
        $userOrgId = OrganizationContext::getCurrentOrganizationId();

        // Allow viewing if:
        // 1. Board type template is a system template (organization_id is null)
        // 2. OR board type template belongs to user's organization
        return $boardType->template->is_system ||
            $boardType->template->organisation_id === $userOrgId;
    }

    public function create(User $user): bool
    {
        // Only admin or users with specific permissions can create board types
        return $user->hasPermission('board.create');
    }

    public function update(User $user, BoardType $boardType): bool
    {
        // Check permission
        if (!$user->hasPermission('board.update')) {
            return false;
        }

        // Load template if not already loaded
        if (!$boardType->relationLoaded('template')) {
            $boardType->load('template');
        }

        // If the template doesn't exist, deny access
        if (!$boardType->template) {
            return false;
        }

        // Get current user's organization ID
        $userOrgId = OrganizationContext::getCurrentOrganizationId();

        // Allow updating if:
        // 1. Board type template belongs to user's organization (NOT system templates)
        return !$boardType->template->is_system &&
            $boardType->template->organisation_id === $userOrgId;

    }

    public function delete(User $user, BoardType $boardType): bool
    {
        // Only admin or users with specific permissions can delete board types
        // May want to prevent deletion if boards are using this type
        if ($boardType->boards()->count() > 0) {
            return false;
        }

        // Check permission
        if (!$user->hasPermission('board.delete')) {
            return false;
        }

        // Load template if not already loaded
        if (!$boardType->relationLoaded('template')) {
            $boardType->load('template');
        }

        // If the template doesn't exist, deny access
        if (!$boardType->template) {
            return false;
        }

        // Get current user's organization ID
        $userOrgId = OrganizationContext::getCurrentOrganizationId();

        // Allow deleting if:
        // 1. Board type template belongs to user's organization (NOT system templates)
        return !$boardType->template->is_system &&
            $boardType->template->organisation_id === $userOrgId;
    }

    public function restore(User $user, BoardType $boardType): bool
    {
        // Check permission
        if (!$user->hasPermission('board.restore')) {
            return false;
        }

        // Load template if not already loaded, with withTrashed to get soft deleted templates
        if (!$boardType->relationLoaded('template')) {
            $boardType->load(['template' => function ($query) {
                $query->withTrashed();
            }]);
        }

        // If the template doesn't exist, deny access
        if (!$boardType->template) {
            return false;
        }

        // Get current user's organization ID
        $userOrgId = OrganizationContext::getCurrentOrganizationId();

        // Allow restoring if:
        // 1. Board type template belongs to user's organization (NOT system templates)
        return !$boardType->template->is_system &&
            $boardType->template->organisation_id === $userOrgId;
    }

    public function forceDelete(User $user, BoardType $boardType): bool
    {
        // Force delete should be restricted to admins
        if (!$user->hasPermission('board.forceDelete')) {
            return false;
        }

        // Load template if not already loaded, with withTrashed to get soft deleted templates
        if (!$boardType->relationLoaded('template')) {
            $boardType->load(['template' => function ($query) {
                $query->withTrashed();
            }]);
        }

        // If the template doesn't exist, deny access
        if (!$boardType->template) {
            return false;
        }

        // Get current user's organization ID
        $userOrgId = OrganizationContext::getCurrentOrganizationId();

        // Allow force deleting if:
        // 1. Board type template belongs to user's organization
        // 2. User is an admin
        return !$boardType->template->is_system &&
            $boardType->template->organisation_id === $userOrgId;
    }
}
