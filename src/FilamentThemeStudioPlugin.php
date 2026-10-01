<?php

declare(strict_types=1);

namespace Hforlife\FilamentThemeStudio;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Hforlife\FilamentThemeStudio\Resources\ThemeResource;
use Hforlife\FilamentThemeStudio\Services\ThemeStylesheet;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class FilamentThemeStudioPlugin implements Plugin
{
    private string $navigationLabel = 'filament-theme-studio::theme-studio.navigation.label';

    private string $navigationGroup = 'filament-theme-studio::theme-studio.navigation.group';

    private int $navigationSort = 100;

    private bool $showInNavigation = true;

    private ?Closure $authorizationCallback = null;

    private ?Closure $customCssAuthorizationCallback = null;

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

        $panel->renderHook(
            PanelsRenderHook::STYLES_AFTER,
            fn () => app(ThemeStylesheet::class)->linkForPanel($panel),
        );
    }

    public function boot(Panel $panel): void
    {
        // The stylesheet hook is registered during panel configuration.
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

    public function authorizeCustomCssUsing(Closure $callback): static
    {
        $this->customCssAuthorizationCallback = $callback;

        return $this;
    }

    public function isCustomCssAuthorized(): bool
    {
        if (! $this->isAuthorized()) {
            return false;
        }

        if ($this->customCssAuthorizationCallback instanceof Closure) {
            return (bool) app()->call($this->customCssAuthorizationCallback);
        }

        return Auth::check() && Gate::allows('manage-theme-studio-custom-css');
    }
}
