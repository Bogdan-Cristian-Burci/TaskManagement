<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Status extends Model
{
    protected $fillable = [
        'name',
        'description',
        'color',
        'is_complete'
    ];

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
