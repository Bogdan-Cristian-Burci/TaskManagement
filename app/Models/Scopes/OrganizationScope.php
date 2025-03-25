<?php

namespace App\Models\Scopes;

use App\Services\OrganizationContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class OrganizationScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param Builder $builder
     * @param Model $model
     * @return void
     */
    public function apply(Builder $builder, Model $model): void
    {
        // Only apply if we're not already joined to the organizations table
        // (prevents issues with relationship queries)
        if (!$this->hasOrganizationJoin($builder)) {
            $organizationId = OrganizationContext::getCurrentOrganizationId();

            if ($organizationId) {
                $builder->where($model->getTable() . '.organisation_id', $organizationId);
            }
        }
    }

    /**
     * Check if the query already has an organization join.
     *
     * @param Builder $builder
     * @return bool
     */
    protected function hasOrganizationJoin(Builder $builder): bool
    {
        $query = $builder->getQuery();

        if (property_exists($query, 'joins') && $query->joins) {
            foreach ($query->joins as $join) {
                if (property_exists($join, 'table') && $join->table === 'organisations') {
                    return true;
                }
            }
        }

        return false;
    }
}
