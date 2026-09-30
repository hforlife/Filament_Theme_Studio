# Filament Theme Studio

Filament Theme Studio is an open-source package that will provide a visual theme editor for Filament panels, including structured settings, advanced CSS editing, previews, controlled publishing, and version history.

The project is currently at **lot 2**: the package foundation and the theme storage engine are ready. Themes can be created, duplicated, activated per panel, versioned, restored, cached, and deleted through a framework-independent service API. The visual editor and CSS generation/injection will be implemented in later lots.

## Requirements

- PHP 8.2 or later
- Laravel 11, 12, or a version supported by the selected Filament release
- Filament 4 or Filament 5

The package supports `filament/filament:^4.0 || ^5.0`. It deliberately does not constrain Livewire directly: Filament 4 resolves Livewire 3, while Filament 5 resolves Livewire 4.

## Installation

Install the package with Composer:

```bash
composer require hforlife/filament-theme-studio
```

Laravel discovers `FilamentThemeStudioServiceProvider` automatically. The package neither runs migrations nor writes to the host application during startup.

Publish and run the package migrations explicitly:

```bash
php artisan vendor:publish --tag="filament-theme-studio-migrations"
php artisan migrate
```

Publishing is optional until the application needs theme persistence. Migrations are never executed automatically by the package.

### Local path installation

For development, add the package as a path repository in the demo application's `composer.json` (adjust the relative path as needed):

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../Filament_Theme_Studio",
            "options": {"symlink": true}
        }
    ]
}
```

Then install the development version:

```bash
composer require hforlife/filament-theme-studio:@dev
```

For a Filament 4 demo application, select Filament 4 first and then install this package:

```bash
composer require filament/filament:^4.0 -W
composer require hforlife/filament-theme-studio:@dev
```

For a Filament 5 demo application, use:

```bash
composer require filament/filament:^5.0 -W
composer require hforlife/filament-theme-studio:@dev
```

In both applications, keep the path repository shown above and register the plugin as described below.

## Panel registration

Register the plugin in the host application's `AdminPanelProvider`:

```php
use Filament\Panel;
use Hforlife\FilamentThemeStudio\FilamentThemeStudioPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugin(FilamentThemeStudioPlugin::make());
}
```

This lot intentionally registers no pages, assets, styles, or scripts. Its migrations are publishable but are never run automatically.

## Theme storage

The published migrations create two portable tables:

- `filament_theme_studio_themes` stores the panel ID, unique panel-scoped slug, JSON settings, custom CSS, active state, and optional creator/updater identifiers.
- `filament_theme_studio_theme_versions` stores immutable settings and CSS snapshots. Versions belong to a theme, have a unique sequential number per theme, and are deleted by cascade with their theme.

Only `ThemeManager` should perform multi-write business operations. It makes activation, version creation, restoration, and deletion transactional and keeps panels isolated.

### Creating and activating a theme

```php
use Hforlife\FilamentThemeStudio\Services\ThemeManager;

$themes = app(ThemeManager::class);

$theme = $themes->createTheme(
    panelId: 'admin',
    name: 'Midnight',
    settings: [
        'primary' => '#2563eb',
        'radius' => '0.5rem',
    ],
    customCss: '.fi-sidebar { border-right: 0; }',
    createdBy: auth()->id(),
);

$themes->activateTheme($theme, updatedBy: auth()->id());
```

Activation disables only the previously active theme from the same panel. Call `$themes->deactivateTheme('admin')` to return that panel to Filament's default theme.

### Versioning and restoration

```php
$version = $themes->createVersion(
    $theme,
    changeNote: 'Before updating the brand palette',
    createdBy: auth()->id(),
);

$restoredTheme = $themes->restoreVersion(
    $theme,
    $version,
    updatedBy: auth()->id(),
);
```

Restoration first creates an automatic snapshot of the current state. Existing snapshots cannot be updated through the model API. The configured version limit removes only the oldest excess snapshots.

## Configuration

The package is enabled by default. Its minimal configuration can be published when an application needs to override it:

```bash
php artisan vendor:publish --tag="filament-theme-studio-config"
```

The published file is `config/filament-theme-studio.php`:

```php
return [
    'enabled' => true,

    'cache' => [
        'enabled' => true,
        'store' => null,
        'ttl' => 3600,
        'prefix' => 'filament-theme-studio',
    ],

    'versions' => [
        'limit' => 20,
    ],
];
```

The active theme cache stores only a theme identifier under a deterministic, panel-specific key such as `filament-theme-studio:panel:admin:active-theme`; the model is reloaded on every read. A `null` store uses Laravel's default cache store, a TTL of `0` caches forever, and `enabled => false` bypasses caching. Mutating operations invalidate only the affected panel.

At this stage, `custom_css` is stored exactly as supplied but is **not injected, parsed, sanitized, or compiled**.

## Development and testing

```bash
composer validate --strict
composer test
composer analyse
composer test:lint
composer test:refactor
```

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).

## Security

See [SECURITY.md](SECURITY.md) to report vulnerabilities privately. Do not disclose security issues in a public issue.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

Filament Theme Studio is open-source software licensed under the [MIT License](LICENSE.md).
