<?php

use Filament\Panel;
use Filament\PanelRegistry;
use Hforlife\FilamentThemeStudio\FilamentThemeStudioPlugin;
use Hforlife\FilamentThemeStudio\Models\Theme;
use Hforlife\FilamentThemeStudio\Services\ThemeManager;
use Hforlife\FilamentThemeStudio\Support\ThemeSettings;

function registerStylesheetPanel(string $id): Panel
{
    $panel = Panel::make()->id($id)->plugin(FilamentThemeStudioPlugin::make());
    app(PanelRegistry::class)->register($panel);

    return $panel;
}

it('serves active theme css with security cache and etag headers', function () {
    registerStylesheetPanel('admin');
    Theme::factory()->forPanel('admin')->active()->create([
        'settings' => ThemeSettings::normalize(['colors' => ['primary' => '#123456']]),
        'custom_css' => '.must-not-ship { display: none; }',
    ]);

    $response = $this->get('/filament-theme-studio/styles/admin.css');
    $etag = $response->headers->get('ETag');

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/css; charset=UTF-8')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertSee('--fts-primary: #123456;', false)
        ->assertDontSee('must-not-ship', false);

    expect($response->headers->get('Cache-Control'))->toContain('public', 'max-age=3600')
        ->and($etag)->toBeString()->toStartWith('"');
    $this->withHeader('If-None-Match', $etag)->get('/filament-theme-studio/styles/admin.css')->assertStatus(304);
});

it('returns an empty stylesheet when the registered panel has no active theme', function () {
    registerStylesheetPanel('admin');
    Theme::factory()->forPanel('admin')->create(['settings' => ThemeSettings::defaults()]);

    $this->get('/filament-theme-studio/styles/admin.css')
        ->assertOk()
        ->assertContent('')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('never serves a theme from another panel', function () {
    registerStylesheetPanel('admin');
    registerStylesheetPanel('customer');
    Theme::factory()->forPanel('customer')->active()->create([
        'settings' => ThemeSettings::normalize(['colors' => ['primary' => '#abcdef']]),
    ]);

    $this->get('/filament-theme-studio/styles/admin.css')->assertOk()->assertContent('');
    $this->get('/filament-theme-studio/styles/customer.css')->assertOk()->assertSee('#abcdef', false);
});

it('rejects unknown malformed pluginless and disabled panel stylesheets', function () {
    registerStylesheetPanel('admin');
    app(PanelRegistry::class)->register(Panel::make()->id('plain'));

    $this->get('/filament-theme-studio/styles/missing.css')->assertNotFound();
    $this->get('/filament-theme-studio/styles/invalid!.css')->assertNotFound();
    $this->get('/filament-theme-studio/styles/plain.css')->assertNotFound();

    config()->set('filament-theme-studio.css.enabled', false);
    $this->get('/filament-theme-studio/styles/admin.css')->assertNotFound();
});

it('changes the stylesheet etag after update activation restoration and deletion', function () {
    registerStylesheetPanel('admin');
    $manager = app(ThemeManager::class);
    $first = Theme::factory()->forPanel('admin')->active()->create(['settings' => ThemeSettings::defaults()]);
    $initial = $this->get('/filament-theme-studio/styles/admin.css')->headers->get('ETag');
    $version = $manager->createVersion($first);

    $manager->updateTheme($first, ['settings' => ThemeSettings::normalize(['colors' => ['primary' => '#112233']])]);
    $updated = $this->get('/filament-theme-studio/styles/admin.css')->headers->get('ETag');
    expect($updated)->not->toBe($initial);

    $second = Theme::factory()->forPanel('admin')->create([
        'settings' => ThemeSettings::normalize(['colors' => ['primary' => '#445566']]),
    ]);
    $manager->activateTheme($second);
    $activated = $this->get('/filament-theme-studio/styles/admin.css')->headers->get('ETag');
    expect($activated)->not->toBe($updated);

    $manager->activateTheme($first->refresh());
    $manager->restoreVersion($first->refresh(), $version);
    $restored = $this->get('/filament-theme-studio/styles/admin.css')->headers->get('ETag');
    expect($restored)->toBe($initial);

    $manager->deleteTheme($first->refresh());
    $this->get('/filament-theme-studio/styles/admin.css')->assertOk()->assertContent('');
});
