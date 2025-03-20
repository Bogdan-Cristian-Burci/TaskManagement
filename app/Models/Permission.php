<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use LaravelIdea\Helper\App\Models\_IH_Permission_C;

class Permission extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'guard_name',
        'category'
    ];

    /**
     * Get templates that have this permission.
     */
    public function templates(): BelongsToMany
    {
        return $this->belongsToMany(RoleTemplate::class, 'template_has_permissions')
            ->withTimestamps();
    }

    /**
     * Group permissions by category.
     */
    public static function getByCategory(): Collection|_IH_Permission_C|array|\Illuminate\Support\Collection
    {
        return self::all()->groupBy('category');
    }
}
