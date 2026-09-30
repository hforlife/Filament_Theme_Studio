<?php

declare(strict_types=1);

namespace Hforlife\FilamentThemeStudio;

use Hforlife\FilamentThemeStudio\Services\ThemeCache;
use Hforlife\FilamentThemeStudio\Services\ThemeManager;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentThemeStudioServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-theme-studio')
            ->hasConfigFile()
            ->hasMigrations([
                'create_filament_theme_studio_themes_table',
                'create_filament_theme_studio_theme_versions_table',
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(ThemeCache::class);
        $this->app->singleton(ThemeManager::class);
    }
}
