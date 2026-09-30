<?php

use Hforlife\FilamentThemeStudio\Models\Theme;
use Hforlife\FilamentThemeStudio\Services\ThemeCache;
use Hforlife\FilamentThemeStudio\Services\ThemeManager;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->manager = app(ThemeManager::class);
    $this->themeCache = app(ThemeCache::class);
});

it('reads and isolates active themes by panel', function () {
    $admin = Theme::factory()->forPanel('admin')->active()->create();
    $staff = Theme::factory()->forPanel('staff')->active()->create();

    expect($this->manager->getActiveTheme('admin')?->is($admin))->toBeTrue()
        ->and($this->manager->getActiveTheme('staff')?->is($staff))->toBeTrue()
        ->and($this->themeCache->key('admin'))->toBe('filament-theme-studio:panel:admin:active-theme');
});

it('invalidates cached active theme after activation and deactivation', function () {
    $old = Theme::factory()->forPanel('admin')->active()->create();
    $next = Theme::factory()->forPanel('admin')->create();
    expect($this->manager->getActiveTheme('admin')?->is($old))->toBeTrue();

    $this->manager->activateTheme($next);
    expect($this->manager->getActiveTheme('admin')?->is($next))->toBeTrue();

    $this->manager->deactivateTheme('admin');
    expect($this->manager->getActiveTheme('admin'))->toBeNull();
});

it('invalidates cache after update and restore', function () {
    $theme = Theme::factory()->forPanel('admin')->active()->create(['settings' => ['color' => 'blue']]);
    $version = $this->manager->createVersion($theme);
    $key = $this->themeCache->key('admin');

    Cache::store()->forever($key, 999999);
    $this->manager->updateTheme($theme, ['settings' => ['color' => 'red']]);
    expect($this->manager->getActiveTheme('admin')?->settings)->toBe(['color' => 'red']);

    Cache::store()->forever($key, 999999);
    $this->manager->restoreVersion($theme->refresh(), $version);
    expect($this->manager->getActiveTheme('admin')?->settings)->toBe(['color' => 'blue']);
});

it('invalidates cache after deletion without affecting other panels', function () {
    $admin = Theme::factory()->forPanel('admin')->active()->create();
    $staff = Theme::factory()->forPanel('staff')->active()->create();
    $this->manager->getActiveTheme('admin');
    $this->manager->getActiveTheme('staff');

    $this->manager->deleteTheme($admin);

    expect($this->manager->getActiveTheme('admin'))->toBeNull()
        ->and($this->manager->getActiveTheme('staff')?->is($staff))->toBeTrue();
});

it('bypasses cache cleanly when it is disabled', function () {
    $theme = Theme::factory()->forPanel('admin')->active()->create();
    $key = $this->themeCache->key('admin');
    Cache::store()->forever($key, 999999);
    config()->set('filament-theme-studio.cache.enabled', false);

    expect($this->manager->getActiveTheme('admin')?->is($theme))->toBeTrue()
        ->and(Cache::store()->get($key))->toBe(999999);
});

it('supports cache entries without expiration when ttl is zero', function () {
    config()->set('filament-theme-studio.cache.ttl', 0);
    $theme = Theme::factory()->forPanel('admin')->active()->create();

    expect($this->manager->getActiveTheme('admin')?->is($theme))->toBeTrue()
        ->and(Cache::store()->has($this->themeCache->key('admin')))->toBeTrue();
});
