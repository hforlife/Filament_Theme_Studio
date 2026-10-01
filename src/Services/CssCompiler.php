<?php

declare(strict_types=1);

namespace Hforlife\FilamentThemeStudio\Services;

use Hforlife\FilamentThemeStudio\Models\Theme;
use Hforlife\FilamentThemeStudio\Support\CompiledThemeCss;
use Hforlife\FilamentThemeStudio\Support\CssConfiguration;
use Hforlife\FilamentThemeStudio\Support\CustomCssConfiguration;
use Hforlife\FilamentThemeStudio\Support\ThemeSettings;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use JsonException;

final readonly class CssCompiler
{
    public function __construct(
        private ColorPaletteGenerator $palettes,
        private CustomCssValidator $customCssValidator,
    ) {}

    public function compile(Theme $theme, bool $includeCustomCss = true): CompiledThemeCss
    {
        $settings = $this->safeSettings($theme);
        $content = $this->build($settings);
        $customCss = $includeCustomCss ? $this->customCss($theme) : '';

        if ($customCss !== '') {
            $content .= "/* Filament Theme Studio: custom CSS */\n{$customCss}\n";
        }

        return new CompiledThemeCss(
            content: $content,
            fingerprint: hash('sha256', $content),
            themeId: (int) $theme->getKey(),
            panelId: $theme->panel_id,
            compilerVersion: CssConfiguration::compilerVersion(),
        );
    }

    public function settingsFingerprint(Theme $theme, bool $includeCustomCss = true): string
    {
        try {
            $encoded = json_encode($this->safeSettings($theme), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException) {
            $encoded = '{}';
        }

        $custom = $includeCustomCss ? implode('|', [
            CustomCssConfiguration::fingerprint(),
            $theme->custom_css_enabled ? '1' : '0',
            (string) $theme->custom_css,
        ]) : 'without-custom-css';

        return hash('sha256', CssConfiguration::compilerVersion() . '|' . $encoded . '|' . $custom);
    }

    /** @return array<string, mixed> */
    private function safeSettings(Theme $theme): array
    {
        try {
            return ThemeSettings::normalize($theme->settings);
        } catch (ValidationException) {
            return ThemeSettings::defaults();
        }
    }

    /** @param array<string, mixed> $settings */
    private function build(array $settings): string
    {
        $colors = $settings['colors'];
        $dark = $settings['dark_colors'];
        $typography = $settings['typography'];
        $shape = $settings['shape'];
        $layout = $settings['layout'];
        $variables = [
            '--fts-primary' => $this->color($colors['primary']),
            '--fts-secondary' => $this->color($colors['secondary']),
            '--fts-success' => $this->color($colors['success']),
            '--fts-warning' => $this->color($colors['warning']),
            '--fts-danger' => $this->color($colors['danger']),
            '--fts-background' => $this->color($colors['background']),
            '--fts-surface' => $this->color($colors['surface']),
            '--fts-text' => $this->color($colors['text']),
            '--fts-sidebar-background' => $this->color($colors['sidebar_background']),
            '--fts-sidebar-text' => $this->color($colors['sidebar_text']),
            '--fts-font-family' => $this->font($typography['font_family']),
            '--fts-base-font-size' => $this->pixels($typography['base_size'], 12, 24),
            '--fts-radius' => $this->pixels($shape['border_radius'], 0, 40),
            '--fts-button-radius' => $this->pixels($shape['button_radius'], 0, 40),
            '--fts-input-radius' => $this->pixels($shape['input_radius'], 0, 40),
            '--fts-card-radius' => $this->pixels($shape['card_radius'], 0, 40),
            '--fts-sidebar-width' => $this->pixels($layout['sidebar_width'], 200, 420),
            '--fts-content-max-width' => $this->contentWidth($layout['content_max_width']),
            '--fts-density-space' => $this->density($layout['density']),
        ];

        foreach (['primary', 'success', 'warning', 'danger'] as $name) {
            foreach ($this->palettes->generate($colors[$name]) as $shade => $value) {
                $variables["--{$name}-{$shade}"] = $value;
            }
        }

        $darkVariables = [
            '--fts-background' => $this->color($dark['background']),
            '--fts-surface' => $this->color($dark['surface']),
            '--fts-text' => $this->color($dark['text']),
            '--fts-sidebar-background' => $this->color($dark['sidebar_background']),
            '--fts-sidebar-text' => $this->color($dark['sidebar_text']),
        ];

        return implode("\n", [
            '/* Filament Theme Studio: structured theme */',
            ':root {',
            $this->declarations($variables),
            '}',
            ':root.dark {',
            $this->declarations($darkVariables),
            '}',
            '.fi-body { background-color: var(--fts-background); color: var(--fts-text); font-family: var(--fts-font-family); font-size: var(--fts-base-font-size); }',
            '.fi-sidebar, .fi-sidebar-header { background-color: var(--fts-sidebar-background); }',
            '.fi-sidebar { --sidebar-width: var(--fts-sidebar-width); }',
            '.fi-sidebar-item-label, .fi-sidebar-group-label { color: var(--fts-sidebar-text); }',
            '.fi-sidebar-item.fi-active > .fi-sidebar-item-btn { background-color: color-mix(in srgb, var(--fts-primary) 12%, transparent); }',
            '.fi-main, .fi-simple-main { max-width: var(--fts-content-max-width); }',
            '.fi-section:not(.fi-section-not-contained), .fi-ta-ctn, .fi-modal-window { background-color: var(--fts-surface); border-radius: var(--fts-card-radius); }',
            '.fi-btn { border-radius: var(--fts-button-radius); }',
            '.fi-input-wrp, .fi-input { border-radius: var(--fts-input-radius); }',
            '.fi-badge, .fi-dropdown-panel { border-radius: var(--fts-radius); }',
            '.fi-main { padding-inline: var(--fts-density-space); }',
            '.fi-section-content, .fi-section-header { padding: var(--fts-density-space); }',
            '',
        ]);
    }

    private function customCss(Theme $theme): string
    {
        if (! CustomCssConfiguration::enabled() || ! $theme->custom_css_enabled || trim((string) $theme->custom_css) === '') {
            return '';
        }

        $result = $this->customCssValidator->validate((string) $theme->custom_css);
        if (! $result->valid) {
            Log::warning('Filament Theme Studio blocked invalid custom CSS during compilation.', [
                'theme_id' => $theme->getKey(),
                'panel_id' => $theme->panel_id,
                'validator_version' => CustomCssConfiguration::VALIDATOR_VERSION,
                'error_count' => count($result->errors),
            ]);

            return '';
        }

        return $result->css;
    }

    /** @param array<string, string> $variables */
    private function declarations(array $variables): string
    {
        return implode("\n", array_map(
            fn (string $name, string $value): string => "  {$name}: {$value};",
            array_keys($variables),
            array_values($variables),
        ));
    }

    private function color(mixed $value): string
    {
        return is_string($value) && preg_match('/^#[0-9A-Fa-f]{6}$/', $value) === 1
            ? strtolower($value)
            : '#000000';
    }

    private function pixels(mixed $value, int $minimum, int $maximum): string
    {
        $number = is_int($value) ? $value : 0;

        return min($maximum, max($minimum, $number)) . 'px';
    }

    private function font(mixed $value): string
    {
        $fonts = [
            'Inter' => 'Inter, ui-sans-serif, system-ui, sans-serif',
            'system-ui' => 'system-ui, sans-serif',
            'Arial' => 'Arial, sans-serif',
            'Helvetica' => 'Helvetica, Arial, sans-serif',
            'Georgia' => 'Georgia, serif',
            'sans-serif' => 'sans-serif',
            'serif' => 'serif',
            'monospace' => 'monospace',
        ];

        return $fonts[is_string($value) ? $value : ''] ?? $fonts['Inter'];
    }

    private function contentWidth(mixed $value): string
    {
        return [
            'full' => '100%', 'screen-xl' => '1280px', 'screen-2xl' => '1536px',
            'screen-3xl' => '1728px', 'screen-4xl' => '1920px', 'screen-5xl' => '2048px',
            'screen-6xl' => '2304px', 'screen-7xl' => '2560px',
        ][is_string($value) ? $value : ''] ?? '100%';
    }

    private function density(mixed $value): string
    {
        return ['compact' => '0.75rem', 'comfortable' => '1.5rem', 'spacious' => '2rem'][is_string($value) ? $value : ''] ?? '1.5rem';
    }
}
