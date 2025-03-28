<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class BoardOrganizationScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (!auth()->check()) {
            return;
        }

        $organisationId = auth()->user()->organisation_id;

        // Filter boards through their project relationship
        $builder->whereHas('project', function($query) use ($organisationId) {
            $query->where('organisation_id', $organisationId);
        });
    }
}
