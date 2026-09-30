<?php

declare(strict_types=1);

namespace Hforlife\FilamentThemeStudio\Models;

use Hforlife\FilamentThemeStudio\Database\Factories\ThemeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $panel_id
 * @property string $name
 * @property string $slug
 * @property array<string, mixed> $settings
 * @property string|null $custom_css
 * @property bool $is_active
 * @property string|null $created_by
 * @property string|null $updated_by
 *
 * @method static Builder<static> active()
 * @method static Builder<static> forPanel(string $panelId)
 */
class Theme extends Model
{
    /** @use HasFactory<ThemeFactory> */
    use HasFactory;

    protected $table = 'filament_theme_studio_themes';

    protected $fillable = [
        'panel_id',
        'name',
        'slug',
        'settings',
        'custom_css',
        'is_active',
        'created_by',
        'updated_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<ThemeVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(ThemeVersion::class, 'theme_id');
    }

    /** @param Builder<Theme> $query */
    public function scopeForPanel(Builder $query, string $panelId): void
    {
        $query->where('panel_id', $panelId);
    }

    /** @param Builder<Theme> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    protected static function newFactory(): ThemeFactory
    {
        return ThemeFactory::new();
    }
}
