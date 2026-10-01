<?php

use Hforlife\FilamentThemeStudio\Exceptions\InvalidCustomCss;
use Hforlife\FilamentThemeStudio\Models\Theme;
use Hforlife\FilamentThemeStudio\Models\ThemeVersion;
use Hforlife\FilamentThemeStudio\Services\CompiledCssCache;
use Hforlife\FilamentThemeStudio\Services\CssCompiler;
use Hforlife\FilamentThemeStudio\Services\CustomCssPreview;
use Hforlife\FilamentThemeStudio\Services\ThemeManager;
use Hforlife\FilamentThemeStudio\Support\ThemeSettings;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    config()->set('filament-theme-studio.custom_css.enabled', true);
});

it('publishes valid CSS transactionally with a snapshot and cache invalidation', function () {
    $manager = app(ThemeManager::class);
    $cache = app(CompiledCssCache::class);
    $theme = Theme::factory()->active()->create(['settings' => ThemeSettings::defaults()]);
    $oldKey = $cache->key($theme);
    $cache->get($theme);

    $published = $manager->publishCustomCss($theme, '.fi-btn { border-radius: 2rem; }', true, 42);

    expect($published->custom_css_enabled)->toBeTrue()
        ->and($published->custom_css)->toBe('.fi-btn { border-radius: 2rem; }')
        ->and($published->versions)->toHaveCount(1)
        ->and($published->versions->first()->custom_css_enabled)->toBeFalse()
        ->and(Cache::has($oldKey))->toBeFalse();

    $compiled = app(CssCompiler::class)->compile($published);
    expect($compiled->content)->toContain('/* Filament Theme Studio: structured theme */')
        ->toContain('/* Filament Theme Studio: custom CSS */')
        ->toContain('.fi-body .fi-btn');
});

it('invalidates outstanding previews after publication', function () {
    $theme = Theme::factory()->create(['settings' => ThemeSettings::defaults()]);
    $preview = app(CustomCssPreview::class);
    $token = $preview->create($theme, 42, '.fi-btn { color: red; }');

    app(ThemeManager::class)->publishCustomCss($theme, '.fi-btn { color: blue; }', true, 42);

    expect($preview->consume($token, $theme, 42))->toBeNull();
});

it('does not save invalid CSS', function () {
    $theme = Theme::factory()->create();

    expect(fn () => app(ThemeManager::class)->publishCustomCss($theme, '.fi-x { position: fixed; }'))
        ->toThrow(InvalidCustomCss::class)
        ->and($theme->refresh()->custom_css)->toBeNull()
        ->and($theme->versions()->count())->toBe(0);
});

it('blocks malicious database CSS again during compilation', function () {
    $theme = Theme::factory()->create([
        'settings' => ThemeSettings::defaults(),
        'custom_css' => '.fi-x { background: url(https://attacker.example/x); }',
        'custom_css_enabled' => true,
    ]);

    expect(app(CssCompiler::class)->compile($theme)->content)
        ->not->toContain('attacker.example')
        ->not->toContain('custom CSS');
});

it('changes fingerprints for source state mode and validator configuration', function () {
    $compiler = app(CssCompiler::class);
    $theme = Theme::factory()->create(['settings' => ThemeSettings::defaults()]);
    $initial = $compiler->settingsFingerprint($theme);

    $theme->forceFill(['custom_css' => '.fi-btn { color: red; }'])->save();
    $source = $compiler->settingsFingerprint($theme->refresh());
    $theme->forceFill(['custom_css_enabled' => true])->save();
    $enabled = $compiler->settingsFingerprint($theme->refresh());
    config()->set('filament-theme-studio.custom_css.max_bytes', 60_000);
    $configuration = $compiler->settingsFingerprint($theme->refresh());

    expect([$initial, $source, $enabled, $configuration])->each->toBeString()
        ->and(count(array_unique([$initial, $source, $enabled, $configuration])))->toBe(4);
});

it('disables custom CSS independently and retains structured output', function () {
    $theme = Theme::factory()->create([
        'settings' => ThemeSettings::defaults(),
        'custom_css' => '.fi-btn { color: red; }',
        'custom_css_enabled' => true,
    ]);

    $disabled = app(ThemeManager::class)->setCustomCssEnabled($theme, false);
    $content = app(CssCompiler::class)->compile($disabled)->content;

    expect($disabled->custom_css_enabled)->toBeFalse()
        ->and($disabled->custom_css)->not->toBeNull()
        ->and($content)->toContain('structured theme')
        ->not->toContain('custom CSS');
});

it('restores source but leaves historical CSS disabled when current validation rejects it', function () {
    $theme = Theme::factory()->create(['settings' => ThemeSettings::defaults()]);
    $version = ThemeVersion::factory()->for($theme)->create([
        'settings' => ThemeSettings::defaults(),
        'custom_css' => '.fi-x { position: fixed; }',
        'custom_css_enabled' => true,
    ]);

    $restored = app(ThemeManager::class)->restoreVersion($theme, $version);

    expect($restored->custom_css)->toBe('.fi-x { position: fixed; }')
        ->and($restored->custom_css_enabled)->toBeFalse();
});

it('isolates short-lived previews by user theme panel and token', function () {
    $preview = app(CustomCssPreview::class);
    $theme = Theme::factory()->forPanel('admin')->create();
    $other = Theme::factory()->forPanel('staff')->create();
    $token = $preview->create($theme, 10, '.fi-btn { color: red; }');

    expect($preview->consume($token, $theme, 10))->toContain('.fi-body .fi-btn')
        ->and($preview->consume($token, $theme, 11))->toBeNull()
        ->and($preview->consume($token, $other, 10))->toBeNull()
        ->and($preview->consume($token . 'x', $theme, 10))->toBeNull();

    $this->travel(11)->minutes();
    expect($preview->consume($token, $theme, 10))->toBeNull();
});

it('requires the feature to be globally enabled for publication and preview', function () {
    config()->set('filament-theme-studio.custom_css.enabled', false);
    $theme = Theme::factory()->create();

    expect(fn () => app(ThemeManager::class)->publishCustomCss($theme, '.fi-btn { color: red; }'))
        ->toThrow(InvalidCustomCss::class)
        ->and(fn () => app(CustomCssPreview::class)->create($theme, 1, '.fi-btn { color: red; }'))
        ->toThrow(InvalidCustomCss::class);
});

it('recovers through the emergency command only with an explicit target', function () {
    $admin = Theme::factory()->forPanel('admin')->create(['custom_css_enabled' => true]);
    $staff = Theme::factory()->forPanel('staff')->create(['custom_css_enabled' => true]);

    $this->artisan('filament-theme-studio:disable-custom-css')->assertExitCode(2);
    $this->artisan('filament-theme-studio:disable-custom-css', ['--panel' => 'admin'])->assertSuccessful();

    expect($admin->refresh()->custom_css_enabled)->toBeFalse()
        ->and($staff->refresh()->custom_css_enabled)->toBeTrue();
});
