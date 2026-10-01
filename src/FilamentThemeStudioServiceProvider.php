<?php

declare(strict_types=1);

namespace Hforlife\FilamentThemeStudio;

use Hforlife\FilamentThemeStudio\Console\Commands\DisableCustomCssCommand;
use Hforlife\FilamentThemeStudio\Http\Controllers\CustomCssPreviewController;
use Hforlife\FilamentThemeStudio\Http\Controllers\ThemeStylesheetController;
use Hforlife\FilamentThemeStudio\Services\CompiledCssCache;
use Hforlife\FilamentThemeStudio\Services\CssCompiler;
use Hforlife\FilamentThemeStudio\Services\CustomCssPreview;
use Hforlife\FilamentThemeStudio\Services\CustomCssValidator;
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
            ->hasCommand(DisableCustomCssCommand::class)
            ->hasMigrations([
                'create_filament_theme_studio_themes_table',
                'create_filament_theme_studio_theme_versions_table',
                'add_custom_css_enabled_to_filament_theme_studio_tables',
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(ThemeCache::class);
        $this->app->singleton(CssCompiler::class);
        $this->app->singleton(CompiledCssCache::class);
        $this->app->singleton(CustomCssValidator::class);
        $this->app->singleton(CustomCssPreview::class);
        $this->app->singleton(ThemeManager::class);
    }

    public function packageBooted(): void
    {
        Route::get(CssConfiguration::routePrefix() . '/{panelId}.css', ThemeStylesheetController::class)
            ->name('filament-theme-studio.styles');

        Route::middleware('web')->get(
            'filament-theme-studio/preview/{panelId}/{theme}/{token}',
            CustomCssPreviewController::class,
        )->name('filament-theme-studio.custom-css.preview');
    }
}
