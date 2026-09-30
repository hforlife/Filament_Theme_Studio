<?php

use Hforlife\FilamentThemeStudio\Models\Theme;
use Hforlife\FilamentThemeStudio\Models\ThemeVersion;
use Illuminate\Support\Facades\Schema;

it('creates both storage tables with their important columns', function () {
    expect(Schema::hasColumns('filament_theme_studio_themes', [
        'id', 'panel_id', 'name', 'slug', 'settings', 'custom_css', 'is_active',
        'created_by', 'updated_by', 'created_at', 'updated_at',
    ]))->toBeTrue()
        ->and(Schema::hasColumns('filament_theme_studio_theme_versions', [
            'id', 'theme_id', 'version', 'settings', 'custom_css', 'change_note',
            'created_by', 'created_at', 'updated_at',
        ]))->toBeTrue();
});

it('cascades theme deletion to its versions', function () {
    $theme = Theme::factory()->create();
    ThemeVersion::factory()->for($theme)->create();

    $theme->delete();

    expect(ThemeVersion::query()->count())->toBe(0);
});

it('can roll back both migrations in a safe order', function () {
    $versions = require __DIR__ . '/../database/migrations/create_filament_theme_studio_theme_versions_table.php.stub';
    $themes = require __DIR__ . '/../database/migrations/create_filament_theme_studio_themes_table.php.stub';

    $versions->down();
    $themes->down();

    expect(Schema::hasTable('filament_theme_studio_theme_versions'))->toBeFalse()
        ->and(Schema::hasTable('filament_theme_studio_themes'))->toBeFalse();
});
