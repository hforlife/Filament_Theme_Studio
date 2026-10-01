<?php

declare(strict_types=1);

namespace Hforlife\FilamentThemeStudio\Services;

use Filament\Panel;
use Hforlife\FilamentThemeStudio\Models\Theme;
use Hforlife\FilamentThemeStudio\Support\CssConfiguration;
use Illuminate\Contracts\Support\Htmlable;
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

        $compiled = $this->css->get($theme);

        return new HtmlString(view('filament-theme-studio::styles-link', [
            'url' => route('filament-theme-studio.styles', [
                'panelId' => $panel->getId(),
                'version' => $compiled->fingerprint,
            ]),
        ])->render());
    }
}
