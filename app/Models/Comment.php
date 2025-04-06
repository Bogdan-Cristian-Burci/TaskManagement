<?php

namespace App\Models;

use App\Traits\HasAuditTrail;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;

/**
 * @property int $id
 * @property string $content
 * @property int $task_id
 * @property int $user_id
 * @property int|null $parent_id
 * @property array|null $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User $user
 * @property-read Task $task
 * @property-read Comment|null $parent
 * @property-read Comment[] $replies
 */
class Comment extends Model
{
    use HasFactory, SoftDeletes, HasAuditTrail;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'content',
        'task_id',
        'user_id',
        'parent_id',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'task_id' => 'integer',
        'user_id' => 'integer',
        'parent_id' => 'integer',
        'deleted_at' => 'datetime',
    ];

    /**
     * Set the metadata attribute with encryption.
     *
     * @param mixed $value
     * @return void
     */
    public function setMetadataAttribute(mixed $value): void
    {
        if (empty($value)) {
            $this->attributes['metadata'] = null;
            return;
        }

        // Convert to JSON and encrypt
        $this->attributes['metadata'] = Crypt::encryptString(json_encode($value));
    }

    /**
     * Get the metadata attribute with decryption.
     *
     * @param mixed $value
     * @return array|null
     */
    public function getMetadataAttribute(mixed $value): ?array
    {
        if (empty($value)) {
            return null;
        }

        try {
            // Decrypt and convert from JSON to array
            return json_decode(Crypt::decryptString($value), true);
        } catch (\Exception $e) {
            \Log::error('Failed to decrypt comment metadata: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get the task that the comment belongs to.
     *
     * @return BelongsTo
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Get the user who wrote the comment.
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the parent comment if this is a reply.
     *
     * @return BelongsTo
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    /**
     * Get the replies to this comment.
     *
     * @return HasMany
     */
    public function replies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id');
    }

    /**
     * Get a truncated version of the comment content for preview.
     *
     * @param int $length
     * @return string
     */
    public function getExcerpt(int $length = 100): string
    {
        return Str::limit(strip_tags($this->content), $length);
    }

    /**
     * Check if the comment has any replies.
     *
     * @return bool
     */
    public function hasReplies(): bool
    {
        return $this->replies()->count() > 0;
    }

    /**
     * Check if the comment is a reply to another comment.
     *
     * @return bool
     */
    public function isReply(): bool
    {
        return $this->parent_id !== null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['content', 'task_id', 'user_id', 'parent_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('comment');
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        // Add task context
        if ($this->task) {
            $activity->properties = $activity->properties->merge([
                'task_name' => $this->task->name ?? 'Unknown',
                'task_number' => $this->task->task_number ?? 'Unknown',
                'project_id' => $this->task->project_id ?? null
            ]);

            // Save the changes
            $activity->save();
        }
    }
}
