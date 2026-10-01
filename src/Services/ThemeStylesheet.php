<?php

declare(strict_types=1);

namespace Hforlife\FilamentThemeStudio\Services;

use Filament\Panel;
use Hforlife\FilamentThemeStudio\Models\Theme;
use Hforlife\FilamentThemeStudio\Support\CssConfiguration;
use Hforlife\FilamentThemeStudio\Support\CustomCssConfiguration;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\HtmlString;

final readonly class ThemeStylesheet
{
    public function __construct(
        private ThemeManager $themes,
        private CompiledCssCache $css,
    ) {}

    public function linkForPanel(Panel $panel): Htmlable
    {
        if (! CssConfiguration::enabled()) {
            return new HtmlString('');
        }

        $theme = $this->themes->getActiveTheme($panel->getId());

        if (! $theme instanceof Theme) {
            return new HtmlString('');
        }

        $includeCustomCss = CustomCssConfiguration::allowOnAuthPages() || ! $this->isAuthenticationPage();
        $compiled = $this->css->get($theme, $includeCustomCss);

        return new HtmlString(view('filament-theme-studio::styles-link', [
            'url' => route('filament-theme-studio.styles', [
                'panelId' => $panel->getId(),
                'version' => $compiled->fingerprint,
                'custom' => $includeCustomCss ? 1 : 0,
            ]),
        ])->render());
    }

    private function isAuthenticationPage(): bool
    {
        $route = Route::current();
        $name = strtolower((string) $route?->getName());
        $action = strtolower((string) $route?->getActionName());
        $context = $name . '|' . $action;

        foreach (['login', 'register', 'password', 'auth', 'verification', 'verify-email', 'two-factor', 'mfa'] as $marker) {
            if (str_contains($context, $marker)) {
                return true;
            }
        }

        return false;
    }
}
