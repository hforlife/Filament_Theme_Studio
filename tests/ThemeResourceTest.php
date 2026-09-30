<?php

use Filament\Facades\Filament;
use Filament\Panel;
use Hforlife\FilamentThemeStudio\FilamentThemeStudioPlugin;
use Hforlife\FilamentThemeStudio\Models\Theme;
use Hforlife\FilamentThemeStudio\Resources\ThemeResource;
use Symfony\Component\HttpKernel\Exception\HttpException;

function themeStudioPanel(FilamentThemeStudioPlugin $plugin): Panel
{
    $panel = Panel::make()->id('admin')->plugin($plugin);
    Filament::setCurrentPanel($panel);

    return $panel;
}

afterEach(fn () => Filament::setCurrentPanel(null));

it('registers its resource explicitly and applies panel navigation configuration', function () {
    $plugin = FilamentThemeStudioPlugin::make()
        ->navigationLabel('Appearance')
        ->navigationGroup('Configuration')
        ->navigationSort(25)
        ->authorizeUsing(fn (): bool => true);
    $panel = themeStudioPanel($plugin);

    expect($panel->getResources())->toContain(ThemeResource::class)
        ->and(ThemeResource::getNavigationLabel())->toBe('Appearance')
        ->and(ThemeResource::getNavigationGroup())->toBe('Configuration')
        ->and(ThemeResource::getNavigationSort())->toBe(25)
        ->and(ThemeResource::shouldRegisterNavigation())->toBeTrue();
});

it('enforces authorization for navigation and direct resource access', function () {
    themeStudioPanel(FilamentThemeStudioPlugin::make()->authorizeUsing(fn (): bool => false));

    expect(ThemeResource::shouldRegisterNavigation())->toBeFalse()
        ->and(ThemeResource::canAccess())->toBeFalse();
});

it('can hide navigation without granting or revoking direct access', function () {
    themeStudioPanel(
        FilamentThemeStudioPlugin::make()
            ->authorizeUsing(fn (): bool => true)
            ->hideFromNavigation(),
    );

    expect(ThemeResource::shouldRegisterNavigation())->toBeFalse()
        ->and(ThemeResource::canAccess())->toBeTrue();
});

it('scopes resource queries and record authorization to the current panel', function () {
    themeStudioPanel(FilamentThemeStudioPlugin::make()->authorizeUsing(fn (): bool => true));
    $admin = Theme::factory()->forPanel('admin')->create();
    $staff = Theme::factory()->forPanel('staff')->create();

    expect(ThemeResource::getEloquentQuery()->pluck('id')->all())->toBe([$admin->id])
        ->and(ThemeResource::canEdit($admin))->toBeTrue()
        ->and(ThemeResource::canEdit($staff))->toBeFalse();

    ThemeResource::authorizeRecord($staff);
})->throws(HttpException::class);

it('ships complete English and French translation catalogs', function (string $locale, string $label) {
    app()->setLocale($locale);

    expect(__('filament-theme-studio::theme-studio.navigation.label'))->toBe($label)
        ->and(__('filament-theme-studio::theme-studio.actions.restore'))
        ->not->toContain('theme-studio.');
})->with([
    'English' => ['en', 'Theme Studio'],
    'French' => ['fr', 'Studio de thème'],
]);
