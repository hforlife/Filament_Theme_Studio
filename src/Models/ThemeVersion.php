<?php

declare(strict_types=1);

namespace Hforlife\FilamentThemeStudio\Models;

use Hforlife\FilamentThemeStudio\Database\Factories\ThemeVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property int $id
 * @property int $theme_id
 * @property int $version
 * @property array<string, mixed> $settings
 * @property string|null $custom_css
 * @property string|null $change_note
 * @property string|null $created_by
 */
class ThemeVersion extends Model
{
    /** @use HasFactory<ThemeVersionFactory> */
    use HasFactory;

    protected $table = 'filament_theme_studio_theme_versions';

    protected $fillable = [
        'theme_id',
        'version',
        'settings',
        'custom_css',
        'change_note',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Theme version snapshots are immutable.');
        });
    }

    /** @return BelongsTo<Theme, $this> */
    public function theme(): BelongsTo
    {
        return $this->belongsTo(Theme::class, 'theme_id');
    }

    protected static function newFactory(): ThemeVersionFactory
    {
        return ThemeVersionFactory::new();
    }
}
