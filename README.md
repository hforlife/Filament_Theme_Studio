# Filament Theme Studio

Filament Theme Studio is an open-source package that will provide a visual theme editor for Filament panels, including structured settings, advanced CSS editing, previews, controlled publishing, and version history.

The project is currently at **lot 3**: the package includes a native Filament administration interface for creating, editing, activating, duplicating, deleting, snapshotting, and restoring panel themes. CSS generation, preview, and runtime injection will be implemented in later lots.

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
        ->plugin(
            FilamentThemeStudioPlugin::make()
                ->navigationLabel('Theme Studio')
                ->navigationGroup('Appearance')
                ->navigationSort(100)
                ->authorizeUsing(fn (): bool => auth()->user()?->is_admin === true),
        );
}
```

The plugin registers its resource only on panels where it is attached. Navigation can be disabled with `->hideFromNavigation()` while keeping direct access available to authorized users. Without an `authorizeUsing()` callback, access requires the authenticated user to pass Laravel's `manage-theme-studio` ability. Authorization is enforced for navigation, direct page access, records, relation managers, and actions.

## Theme Studio interface

The resource is scoped to the current Filament panel. Its editor covers the canonical light and dark palettes, sidebar colors and width, local font families and base size, component radii, content width, and interface density. Values are validated on the server and unknown settings keys are preserved when an existing theme is edited.

The canonical `settings` document is:

```php
[
    'colors' => [
        'primary' => '#3b82f6',
        'secondary' => '#64748b',
        'success' => '#22c55e',
        'warning' => '#f59e0b',
        'danger' => '#ef4444',
        'background' => '#ffffff',
        'surface' => '#ffffff',
        'text' => '#111827',
        'sidebar_background' => '#ffffff',
        'sidebar_text' => '#374151',
    ],
    'dark_colors' => [
        'background' => '#09090b',
        'surface' => '#18181b',
        'text' => '#fafafa',
        'sidebar_background' => '#18181b',
        'sidebar_text' => '#e4e4e7',
    ],
    'typography' => ['font_family' => 'Inter', 'base_size' => 16],
    'shape' => [
        'border_radius' => 8,
        'button_radius' => 8,
        'input_radius' => 8,
        'card_radius' => 12,
    ],
    'layout' => [
        'sidebar_width' => 288,
        'content_max_width' => 'full',
        'density' => 'comfortable',
    ],
]
```

This stable, language-independent structure prepares the data consumed by the CSS engine planned for lot 4.

The list and edit pages expose the following operations:

- activate or deactivate a panel theme;
- duplicate a theme without copying its history or active state;
- create an immutable snapshot;
- browse version history and restore a snapshot after automatic backup;
- delete a theme and its version history after confirmation.

The interface ships in English and French and uses only native Filament components. This release does not inject, compile, sanitize, or preview CSS.

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

At this stage, `custom_css` is stored exactly as supplied but is **not exposed by the visual editor, injected, parsed, sanitized, or compiled**.

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
