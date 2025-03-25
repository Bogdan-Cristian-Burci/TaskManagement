<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property integer $id
 * @property string $name
 * @property string $description
 * @property integer $template_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon $deleted_at
 * @property BoardTemplate $template
 * @property Board[] $boards
 */

class BoardType extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'template_id',
    ];

    protected $casts = [
        'id' => 'integer',
        'template_id' => 'integer',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the boards that use this board type.
     *
     * @return HasMany
     */
    public function boards(): HasMany
    {
        return $this->hasMany(Board::class);
    }

    /**
     * Get the template associated with this board type.
     *
     * @return BelongsTo
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(BoardTemplate::class);
    }

    /**
     * Create a board using this board type's template.
     *
     * @param array $attributes Board attributes
     * @return Board|null
     */
    public function createBoard(array $attributes): ?Board
    {
        if (!$this->template) {
            return null;
        }

        return $this->template->createBoard(array_merge($attributes, [
            'board_type_id' => $this->id
        ]));
    }

    /**
     * Get a board type using a specific template key.
     *
     * @param string $templateKey
     * @param string|null $name
     * @param string|null $description
     * @return self
     * @throws \Exception
     */
    public static function getOrCreateWithTemplate(string $templateKey, ?string $name = null, ?string $description = null): self
    {
        $template = BoardTemplate::findByKey($templateKey);

        if (!$template) {
            // If template doesn't exist, sync from config first and try again
            BoardTemplate::syncFromConfig();
            $template = BoardTemplate::findByKey($templateKey);

            if (!$template) {
                throw new \Exception("Board template with key '{$templateKey}' not found.");
            }
        }

        // Look for existing board type with this template
        $boardType = self::where('template_id', $template->id)->first();

        if (!$boardType) {
            // Create new board type
            $boardType = self::create([
                'name' => $name ?? $template->name,
                'description' => $description ?? $template->description,
                'template_id' => $template->id
            ]);
        }

        return $boardType;
    }
}
