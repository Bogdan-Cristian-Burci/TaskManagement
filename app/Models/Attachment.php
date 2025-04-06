<?php

namespace App\Models;

use App\Enums\ChangeTypeEnum;
use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;

/**
 * @property integer $id
 * @property integer $task_id
 * @property integer $user_id
 * @property string $filename
 * @property string $original_filename
 * @property string $file_path
 * @property integer $file_size
 * @property string $file_type
 * @property string $description
 * @property string $created_at
 * @property string $updated_at
 * @property Task $task
 * @property User $user
 * @property array|null $metadata
 * @property string $disk
 */
class Attachment extends Model
{
    use HasFactory, SoftDeletes, HasAuditTrail;

    protected $fillable = [
        'task_id',
        'user_id',
        'filename',
        'original_filename',
        'file_path',
        'file_size',
        'file_type',
        'description',
        'metadata',
        'disk',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
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
        if (is_null($value)) {
            $this->attributes['metadata'] = null;
            return;
        }

        // Convert to JSON and encrypt
        $this->attributes['metadata'] = \Illuminate\Support\Facades\Crypt::encryptString(json_encode($value));
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
            return json_decode(\Illuminate\Support\Facades\Crypt::decryptString($value), true);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to decrypt metadata: ' . $e->getMessage());
            return null;
        }
    }
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get formatted file size in KB, MB, etc.
     *
     * @return string
     */
    public function getFormattedFileSizeAttribute(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Check if attachment is an image
     *
     * @return bool
     */
    public function getIsImageAttribute(): bool
    {
        return str_starts_with($this->file_type, 'image/');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['filename', 'filepath', 'filesize', 'filetype', 'task_id', 'user_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('attachment');
    }

    public function tapActivity(Activity $activity, string $eventName)
    {
        if ($this->task) {
            $activity->change_type_id = ChangeType::where('name', ChangeTypeEnum::ATTACHMENT->value)->value('id');
            $activity->properties = $activity->properties->merge([
                'task_name' => $this->task->name ?? 'Unknown',
                'task_number' => $this->task->task_number ?? 'Unknown'
            ]);

            // Save the changes to the activity
            $activity->save();
        }
    }
}
