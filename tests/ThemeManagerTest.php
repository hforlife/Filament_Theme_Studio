<?php

use Hforlife\FilamentThemeStudio\Exceptions\DuplicateThemeSlug;
use Hforlife\FilamentThemeStudio\Exceptions\InvalidThemeSettings;
use Hforlife\FilamentThemeStudio\Exceptions\ThemeVersionNotFound;
use Hforlife\FilamentThemeStudio\Models\Theme;
use Hforlife\FilamentThemeStudio\Models\ThemeVersion;
use Hforlife\FilamentThemeStudio\Services\ThemeManager;
use Illuminate\Database\Eloquent\Model;

beforeEach(function () {
    $this->manager = app(ThemeManager::class);
});

it('creates a theme and preserves JSON settings and custom CSS', function () {
    $theme = $this->manager->createTheme(
        panelId: 'admin',
        name: 'Midnight',
        settings: ['primary' => '#112233', 'nested' => ['radius' => 8]],
        customCss: '.fi-body { color: red; }',
        createdBy: 42,
    )->refresh();

    expect($theme->slug)->toBe('midnight')
        ->and($theme->settings)->toBe(['primary' => '#112233', 'nested' => ['radius' => 8]])
        ->and($theme->custom_css)->toBe('.fi-body { color: red; }')
        ->and($theme->is_active)->toBeFalse()
        ->and($theme->created_by)->toBe('42');
});

it('isolates slug uniqueness by panel', function () {
    $this->manager->createTheme('admin', 'Shared');
    $other = $this->manager->createTheme('staff', 'Shared');

    expect($other->slug)->toBe('shared')
        ->and(fn () => $this->manager->createTheme('admin', 'Shared'))
        ->toThrow(DuplicateThemeSlug::class);
});

it('provides panel and active scopes', function () {
    Theme::factory()->forPanel('admin')->active()->create();
    Theme::factory()->forPanel('admin')->create();
    Theme::factory()->forPanel('staff')->active()->create();

    expect(Theme::query()->forPanel('admin')->count())->toBe(2)
        ->and(Theme::query()->forPanel('admin')->active()->count())->toBe(1);
});

it('updates allowed fields without moving a theme to another panel', function () {
    $theme = $this->manager->createTheme('admin', 'Original');

    $updated = $this->manager->updateTheme($theme, [
        'panel_id' => 'staff',
        'name' => 'Updated',
        'settings' => ['gray' => 'slate'],
        'custom_css' => null,
    ], 'editor');

    expect($updated->panel_id)->toBe('admin')
        ->and($updated->name)->toBe('Updated')
        ->and($updated->settings)->toBe(['gray' => 'slate'])
        ->and($updated->updated_by)->toBe('editor');
});

it('activates only one theme in its panel without affecting another panel', function () {
    $old = Theme::factory()->forPanel('admin')->active()->create();
    $next = Theme::factory()->forPanel('admin')->create();
    $staff = Theme::factory()->forPanel('staff')->active()->create();

    $this->manager->activateTheme($next);

    expect($old->refresh()->is_active)->toBeFalse()
        ->and($next->refresh()->is_active)->toBeTrue()
        ->and($staff->refresh()->is_active)->toBeTrue()
        ->and(Theme::query()->forPanel('admin')->active()->count())->toBe(1);
});

it('deactivates a panel safely even when no theme is active', function () {
    $theme = Theme::factory()->forPanel('admin')->active()->create();
    $this->manager->deactivateTheme('admin');
    $this->manager->deactivateTheme('missing');

    expect($theme->refresh()->is_active)->toBeFalse();
});

it('rolls activation back when the final write fails', function () {
    $old = Theme::factory()->forPanel('admin')->active()->create();
    $next = Theme::factory()->forPanel('admin')->create();

    Theme::updating(function (Theme $theme) use ($next): void {
        if ($theme->name === $next->name) {
            throw new LogicException('Simulated write failure');
        }
    });

    expect(fn () => $this->manager->activateTheme($next))->toThrow(LogicException::class)
        ->and($old->refresh()->is_active)->toBeTrue()
        ->and($next->refresh()->is_active)->toBeFalse();
});

it('duplicates content without activation or version history', function () {
    $source = Theme::factory()->active()->create([
        'settings' => ['primary' => '#abcdef'],
        'custom_css' => 'body { opacity: .9; }',
    ]);
    ThemeVersion::factory()->for($source)->create();

    $copy = $this->manager->duplicateTheme($source, 'Copy', 'copy-theme');

    expect($copy->settings)->toBe($source->settings)
        ->and($copy->custom_css)->toBe($source->custom_css)
        ->and($copy->slug)->toBe('copy-theme')
        ->and($copy->is_active)->toBeFalse()
        ->and($copy->versions()->count())->toBe(0);
});

it('creates faithful incrementing immutable version snapshots', function () {
    $theme = Theme::factory()->create([
        'settings' => ['primary' => 'blue'],
        'custom_css' => '.a {}',
    ]);

    $first = $this->manager->createVersion($theme, 'Initial', '7');
    $this->manager->updateTheme($theme, ['settings' => ['primary' => 'red']]);
    $second = $this->manager->createVersion($theme->refresh());

    expect($first->version)->toBe(1)
        ->and($first->settings)->toBe(['primary' => 'blue'])
        ->and($first->custom_css)->toBe('.a {}')
        ->and($second->version)->toBe(2)
        ->and(fn () => $first->update(['change_note' => 'Changed']))
        ->toThrow(LogicException::class);
});

it('backs up current state before restoring a version', function () {
    $theme = Theme::factory()->create(['settings' => ['color' => 'blue'], 'custom_css' => '.blue {}']);
    $snapshot = $this->manager->createVersion($theme, 'Blue');
    $this->manager->updateTheme($theme, ['settings' => ['color' => 'red'], 'custom_css' => '.red {}']);
    config()->set('filament-theme-studio.versions.limit', 1);

    $restored = $this->manager->restoreVersion($theme->refresh(), $snapshot, '9');
    $backup = $restored->versions()->where('version', 2)->firstOrFail();

    expect($restored->settings)->toBe(['color' => 'blue'])
        ->and($restored->custom_css)->toBe('.blue {}')
        ->and($backup->settings)->toBe(['color' => 'red'])
        ->and($backup->custom_css)->toBe('.red {}')
        ->and($snapshot->refresh()->settings)->toBe(['color' => 'blue'])
        ->and($restored->versions()->count())->toBe(2);
});

it('refuses to restore a version belonging to another theme', function () {
    $theme = Theme::factory()->create();
    $other = Theme::factory()->create();
    $version = ThemeVersion::factory()->for($other)->create();

    expect(fn () => $this->manager->restoreVersion($theme, $version))
        ->toThrow(ThemeVersionNotFound::class)
        ->and($theme->versions()->count())->toBe(0);
});

it('enforces and validates the configured version limit', function () {
    config()->set('filament-theme-studio.versions.limit', 2);
    $theme = Theme::factory()->create();

    $this->manager->createVersion($theme);
    $this->manager->createVersion($theme);
    $this->manager->createVersion($theme);

    expect($theme->versions()->pluck('version')->all())->toBe([2, 3]);

    config()->set('filament-theme-studio.versions.limit', 0);

    expect(fn () => $this->manager->createVersion($theme))
        ->toThrow(InvalidThemeSettings::class)
        ->and($theme->versions()->count())->toBe(2);
});

it('deletes only the requested theme and cascades its versions', function () {
    $theme = Theme::factory()->forPanel('admin')->active()->create();
    $other = Theme::factory()->forPanel('staff')->active()->create();
    ThemeVersion::factory()->for($theme)->create();

    $this->manager->deleteTheme($theme);

    expect(Theme::query()->find($theme->getKey()))->toBeNull()
        ->and(ThemeVersion::query()->count())->toBe(0)
        ->and($other->refresh()->is_active)->toBeTrue()
        ->and(Theme::query()->forPanel('admin')->active()->exists())->toBeFalse();
});

afterEach(function () {
    Theme::flushEventListeners();
    Model::clearBootedModels();
});
