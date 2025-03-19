<?php

namespace App\Services;

use App\Models\Organisation;

class OrganizationContext
{
    protected static ?int $currentOrganizationId = null;

    public static function setCurrentOrganization(?int $organizationId): void
    {
        static::$currentOrganizationId = $organizationId;
    }

    public static function getCurrentOrganizationId(): ?int
    {
        // If we have a value set, return it
        if (static::$currentOrganizationId !== null) {
            return static::$currentOrganizationId;
        }

        // Fallback to logged-in user's organization
        if (auth()->check()) {
            return auth()->user()->organisation_id;
        }

        return null;
    }

    public static function clear(): void
    {
        static::$currentOrganizationId = null;
    }

    public static function getOrganisation(): ?Organisation
    {
        $id = self::getCurrentOrganizationId();
        return $id ? Organisation::find($id) : null;
    }
}
