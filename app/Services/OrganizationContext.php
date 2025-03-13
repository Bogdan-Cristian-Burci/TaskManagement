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
        return static::$currentOrganizationId;
    }

    public static function clear(): void
    {
        static::$currentOrganizationId = null;
    }
}
