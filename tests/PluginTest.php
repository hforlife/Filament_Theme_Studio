<?php

use Filament\Contracts\Plugin;
use Filament\Panel;
use Hforlife\FilamentThemeStudio\FilamentThemeStudioPlugin;
use Hforlife\FilamentThemeStudio\FilamentThemeStudioServiceProvider;
use Hforlife\FilamentThemeStudio\Resources\ThemeResource;
use Hforlife\FilamentThemeStudio\Services\ThemeCache;
use Hforlife\FilamentThemeStudio\Services\ThemeManager;
use Illuminate\Support\ServiceProvider;

it('boots the package and loads its configuration', function () {
    expect(app()->getProvider(FilamentThemeStudioServiceProvider::class))
        ->toBeInstanceOf(FilamentThemeStudioServiceProvider::class)
        ->and(config('filament-theme-studio.enabled'))->toBeTrue();
});

it('declares its service provider for Laravel package discovery', function () {
    $composer = json_decode(
        (string) file_get_contents(__DIR__ . '/../composer.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect($composer['extra']['laravel']['providers'])
        ->toContain(FilamentThemeStudioServiceProvider::class);
});

it('publishes both migrations without running them automatically', function () {
    $paths = ServiceProvider::pathsToPublish(
        FilamentThemeStudioServiceProvider::class,
        'filament-theme-studio-migrations',
    );

    expect($paths)->toHaveCount(2)
        ->and(array_keys($paths))->each->toEndWith('.php.stub')
        ->and(array_values($paths))->each->toEndWith('.php');
});

it('registers the theme services as singletons', function () {
    expect(app(ThemeManager::class))->toBe(app(ThemeManager::class))
        ->and(app(ThemeCache::class))->toBe(app(ThemeCache::class));
});

it('resolves the plugin from the container and exposes its stable id', function () {
    $plugin = FilamentThemeStudioPlugin::make();

    expect($plugin)
        ->toBeInstanceOf(FilamentThemeStudioPlugin::class)
        ->toBeInstanceOf(Plugin::class)
        ->and($plugin->getId())->toBe('filament-theme-studio');
});

it('registers and boots in a panel without error', function () {
    $plugin = FilamentThemeStudioPlugin::make();
    $panel = Panel::make()->id('admin')->plugin($plugin);

    $plugin->register($panel);
    $plugin->boot($panel);

    expect($panel->getPlugin('filament-theme-studio'))->toBe($plugin)
        ->and($panel->getResources())->toContain(ThemeResource::class);
});
