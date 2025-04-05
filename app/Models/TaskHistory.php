<?php

namespace App\Models;

use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property integer $id
 * @property integer $task_id
 * @property integer $user_id
 * @property string $field_changed
 * @property integer $change_type_id
 * @property string $old_value
 * @property string $new_value
 * @property array $old_data
 * @property array $new_data
 * @property Task $task
 * @property User $user
 * @property ChangeType $changeType
 */
class TaskHistory extends Model
{

    use HasFactory, HasAuditTrail;

    protected $fillable = [
        'task_id',
        'user_id',
        'field_changed',
        'change_type_id',
        'old_value',
        'new_value',
        'old_data',
        'new_data',
    ];

    protected $casts = [
        'task_id' => 'integer',
        'user_id' => 'integer',
        'change_type_id' => 'integer',
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

    public function changeType(): BelongsTo
    {
        return $this->belongsTo(ChangeType::class, 'change_type_id');
    }
}
