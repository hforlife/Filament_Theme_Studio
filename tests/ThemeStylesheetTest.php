<?php

use Filament\Panel;
use Hforlife\FilamentThemeStudio\FilamentThemeStudioPlugin;
use Hforlife\FilamentThemeStudio\Models\Theme;
use Hforlife\FilamentThemeStudio\Services\ThemeStylesheet;
use Hforlife\FilamentThemeStudio\Support\ThemeSettings;

it('renders a versioned external stylesheet link only for an active theme', function () {
    $panel = Panel::make()->id('admin')->plugin(FilamentThemeStudioPlugin::make());
    $stylesheet = app(ThemeStylesheet::class);

    expect($stylesheet->linkForPanel($panel)->toHtml())->toBe('');

    Theme::factory()->forPanel('admin')->active()->create(['settings' => ThemeSettings::defaults()]);
    $html = $stylesheet->linkForPanel($panel)->toHtml();

    expect($html)->toContain('<link rel="stylesheet"')
        ->and($html)->toContain('/filament-theme-studio/styles/admin.css?version=')
        ->and($html)->not->toContain('<style>');
});

it('does not render a link when structured css is disabled', function () {
    $panel = Panel::make()->id('admin')->plugin(FilamentThemeStudioPlugin::make());
    Theme::factory()->forPanel('admin')->active()->create(['settings' => ThemeSettings::defaults()]);
    config()->set('filament-theme-studio.css.enabled', false);

    expect(app(ThemeStylesheet::class)->linkForPanel($panel)->toHtml())->toBe('');
});
