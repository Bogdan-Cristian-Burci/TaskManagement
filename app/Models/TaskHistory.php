<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property integer $id
 * @property integer $task_id
 * @property integer $user_id
 * @property string $field_changed
 * @property string $old_value
 * @property string $new_value
 * @property array $old_data
 * @property array $new_data
 * @property Task $task
 * @property User $user
 */
class TaskHistory extends Model
{

    use HasFactory;

    protected $fillable = [
        'task_id',
        'user_id',
        'field_changed',
        'old_value',
        'new_value',
        'old_data',
        'new_data',
    ];

    protected $casts = [
        'old_data' => 'array',
        'new_data' => 'array',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
