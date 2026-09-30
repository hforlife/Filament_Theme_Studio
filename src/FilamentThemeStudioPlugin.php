<?php

declare(strict_types=1);

namespace Hforlife\FilamentThemeStudio;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Hforlife\FilamentThemeStudio\Resources\ThemeResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class FilamentThemeStudioPlugin implements Plugin
{
    private string $navigationLabel = 'filament-theme-studio::theme-studio.navigation.label';

    private string $navigationGroup = 'filament-theme-studio::theme-studio.navigation.group';

    private int $navigationSort = 100;

    private bool $showInNavigation = true;

    private ?Closure $authorizationCallback = null;

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
        $panel->resources([
            ThemeResource::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
        // Intentionally empty until later feature lots boot panel resources.
    }

    public function navigationLabel(string $label): static
    {
        $this->navigationLabel = $label;

        return $this;
    }

    public function getNavigationLabel(): string
    {
        return __($this->navigationLabel);
    }

    public function navigationGroup(string $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    public function getNavigationGroup(): string
    {
        return __($this->navigationGroup);
    }

    public function navigationSort(int $sort): static
    {
        $this->navigationSort = $sort;

        return $this;
    }

    public function getNavigationSort(): int
    {
        return $this->navigationSort;
    }

    public function showInNavigation(bool $condition = true): static
    {
        $this->showInNavigation = $condition;

        return $this;
    }

    public function hideFromNavigation(bool $condition = true): static
    {
        $this->showInNavigation = ! $condition;

        return $this;
    }

    public function shouldShowInNavigation(): bool
    {
        return $this->showInNavigation;
    }

    public function authorizeUsing(Closure $callback): static
    {
        $this->authorizationCallback = $callback;

        return $this;
    }

    public function isAuthorized(): bool
    {
        if ($this->authorizationCallback instanceof Closure) {
            return (bool) app()->call($this->authorizationCallback);
        }

        return Auth::check() && Gate::allows('manage-theme-studio');
    }
}
