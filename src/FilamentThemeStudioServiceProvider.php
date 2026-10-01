<?php

declare(strict_types=1);

namespace Hforlife\FilamentThemeStudio;

use Hforlife\FilamentThemeStudio\Http\Controllers\ThemeStylesheetController;
use Hforlife\FilamentThemeStudio\Services\CompiledCssCache;
use Hforlife\FilamentThemeStudio\Services\CssCompiler;
use Hforlife\FilamentThemeStudio\Services\ThemeCache;
use Hforlife\FilamentThemeStudio\Services\ThemeManager;
use Hforlife\FilamentThemeStudio\Support\CssConfiguration;
use Illuminate\Support\Facades\Route;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentThemeStudioServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-theme-studio')
            ->hasConfigFile()
            ->hasTranslations()
            ->hasViews()
            ->hasMigrations([
                'create_filament_theme_studio_themes_table',
                'create_filament_theme_studio_theme_versions_table',
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(ThemeCache::class);
        $this->app->singleton(CssCompiler::class);
        $this->app->singleton(CompiledCssCache::class);
        $this->app->singleton(ThemeManager::class);
    }

    public function packageBooted(): void
    {
        Route::get(CssConfiguration::routePrefix() . '/{panelId}.css', ThemeStylesheetController::class)
            ->name('filament-theme-studio.styles');
    }
}
