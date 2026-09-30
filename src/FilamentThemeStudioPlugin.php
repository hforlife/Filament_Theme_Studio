<?php

declare(strict_types=1);

namespace Hforlife\FilamentThemeStudio;

use Filament\Contracts\Plugin;
use Filament\Panel;

class FilamentThemeStudioPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'filament-theme-studio';
    }

    public function register(Panel $panel): void
    {
        // Intentionally empty until later feature lots register panel resources.
    }

    public function boot(Panel $panel): void
    {
        // Intentionally empty until later feature lots boot panel resources.
    }
}
