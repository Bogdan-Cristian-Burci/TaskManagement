<?php

namespace App\Models;

use App\Models\Scopes\OrganizationScope;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class BoardTemplate extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'name',
        'description',
        'key',
        'organisation_id',
        'columns_structure',
        'settings',
        'is_system',
        'is_active',
        'created_by'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'columns_structure' => 'array',
        'settings' => 'array',
        'is_system' => 'boolean',
        'is_active' => 'boolean',
        'deleted_at' => 'datetime'
    ];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted(): void
    {
        // Apply organization scope for non-system templates
        static::addGlobalScope('withoutSystem', function ($builder) {
            $builder->where(function ($query) {
                $query->where('is_system', false)
                    ->orWhereNull('organisation_id');
            });
        });

        static::addGlobalScope(new OrganizationScope);

        // Clear cache when templates are modified
        static::saved(function ($template) {
            self::clearCaches();
        });

        static::deleted(function ($template) {
            self::clearCaches();
        });
    }

    /**
     * Get the organization that owns the template.
     *
     * @return BelongsTo
     */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /**
     * Get the user who created this template.
     *
     * @return BelongsTo
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the board types that use this template.
     *
     * @return HasMany
     */
    public function boardTypes(): HasMany
    {
        return $this->hasMany(BoardType::class, 'template_id');
    }

    /**
     * Create a board based on this template.
     *
     * @param array $boardData
     * @return Board
     */
    public function createBoard(array $boardData): Board
    {
        // Create the board
        $board = Board::create($boardData);

        // Create the columns based on the template
        if (!empty($this->columns_structure)) {
            $position = 0;
            foreach ($this->columns_structure as $column) {
                $position++;
                $board->columns()->create([
                    'name' => $column['name'],
                    'position' => $position,
                    'color' => $column['color'] ?? '#6C757D',
                    'wip_limit' => $column['wip_limit'] ?? null,
                    'maps_to_status_id' => $column['status_id'] ?? null,
                    'allowed_transitions' => $column['allowed_transitions'] ?? null,
                ]);
            }
        }

        return $board->refresh();
    }

    /**
     * Get all system templates.
     *
     * @return Collection
     */
    public static function getSystemTemplates(): Collection
    {
        $cacheKey = 'board_templates_system';

        return Cache::remember($cacheKey, 86400, function () {
            return self::withoutGlobalScope('withoutSystem')
                ->withoutGlobalScope(OrganizationScope::class)
                ->where('is_system', true)
                ->get();
        });
    }

    /**
     * Get a template by its key.
     *
     * @param string $key
     * @return self|null
     */
    public static function findByKey(string $key): ?self
    {
        $cacheKey = "board_template_key_{$key}";

        return Cache::remember($cacheKey, 86400, function () use ($key) {
            return self::withoutGlobalScope('withoutSystem')
                ->withoutGlobalScope(OrganizationScope::class)
                ->where('key', $key)
                ->first();
        });
    }

    /**
     * Sync system templates from config.
     *
     * @return void
     */
    public static function syncFromConfig(): void
    {
        $templates = config('board_templates');

        foreach ($templates as $key => $templateData) {
            self::withoutGlobalScope('withoutSystem')
                ->withoutGlobalScope(OrganizationScope::class)
                ->updateOrCreate(
                    ['key' => $key],
                    [
                        'name' => $templateData['name'],
                        'description' => $templateData['description'] ?? null,
                        'columns_structure' => $templateData['columns_structure'],
                        'settings' => $templateData['settings'] ?? [],
                        'is_system' => true,
                        'is_active' => true,
                        'organisation_id' => null
                    ]
                );
        }

        // Clear caches after sync
        self::clearCaches();
    }

    /**
     * Clear related caches.
     *
     * @return void
     */
    public static function clearCaches(): void
    {
        Cache::forget('board_templates_system');

        // Clear individual template caches
        $templates = self::withoutGlobalScope('withoutSystem')
            ->withoutGlobalScope(OrganizationScope::class)
            ->get();

        foreach ($templates as $template) {
            Cache::forget("board_template_key_{$template->key}");
        }
    }

    /**
     * Create a custom template for an organization.
     *
     * @param int $organisationId
     * @param string $name
     * @param string $description
     * @param array $columnsStructure
     * @param array $settings
     * @param int|null $createdBy
     * @return self
     */
    public static function createCustom(
        int $organisationId,
        string $name,
        string $description,
        array $columnsStructure,
        array $settings = [],
        int $createdBy = null
    ): self {
        $key = 'custom_' . strtolower(str_replace(' ', '_', $name)) . '_' . time();

        return self::create([
            'name' => $name,
            'description' => $description,
            'key' => $key,
            'organisation_id' => $organisationId,
            'columns_structure' => $columnsStructure,
            'settings' => $settings,
            'is_system' => false,
            'is_active' => true,
            'created_by' => $createdBy
        ]);
    }

    /**
     * Duplicate an existing template for an organization.
     *
     * @param int $organisationId
     * @param int $templateId
     * @param string|null $newName
     * @param int|null $createdBy
     * @return self
     */
    public static function duplicateExisting(
        int $organisationId,
        int $templateId,
        ?string $newName = null,
        int $createdBy = null
    ): self {
        $sourceTemplate = self::findOrFail($templateId);

        $name = $newName ?? $sourceTemplate->name . ' (Copy)';
        $key = 'custom_' . strtolower(str_replace(' ', '_', $name)) . '_' . time();

        return self::create([
            'name' => $name,
            'description' => $sourceTemplate->description,
            'key' => $key,
            'organisation_id' => $organisationId,
            'columns_structure' => $sourceTemplate->columns_structure,
            'settings' => $sourceTemplate->settings,
            'is_system' => false,
            'is_active' => true,
            'created_by' => $createdBy
        ]);
    }
}
