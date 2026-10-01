# Filament Theme Studio

Filament Theme Studio is an open-source package that will provide a visual theme editor for Filament panels, including structured settings, advanced CSS editing, previews, controlled publishing, and version history.

The project is currently at **lot 5**: trusted administrators can validate, preview, publish, disable, and recover strictly constrained custom CSS in addition to the structured theme.

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
                ->authorizeUsing(fn (): bool => auth()->user()?->can('manage-theme-studio') ?? false)
                ->authorizeCustomCssUsing(fn (): bool => auth()->user()?->can('manage-theme-studio-custom-css') ?? false),
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

This stable, language-independent structure is the only input consumed by the CSS engine.

The list and edit pages expose the following operations:

- activate or deactivate a panel theme;
- duplicate a theme without copying its history or active state;
- create an immutable snapshot;
- browse version history and restore a snapshot after automatic backup;
- delete a theme and its version history after confirmation.

The interface ships in English and French and uses only native Filament components.

## Structured CSS engine

When a panel has the plugin and an active theme, the official `PanelsRenderHook::STYLES_AFTER` hook adds a versioned external stylesheet link. No link is rendered for pluginless panels, disabled CSS, or panels without an active theme. The endpoint is:

```text
/filament-theme-studio/styles/{panelId}.css?version={SHA-256}
```

The response uses `text/css`, `X-Content-Type-Options: nosniff`, configurable HTTP cache headers, a strong SHA-256 `ETag`, and conditional `304 Not Modified` responses. It resolves only the active theme for the requested, registered plugin panel; callers cannot select an arbitrary theme.

The compiler exposes plugin-owned `--fts-*` variables for semantic colors, light and dark surfaces, typography, radii, sidebar width, content width, and density. It also generates deterministic Filament palettes (`50` through `950`) for primary, success, warning, and danger colors. Palette interpolation is intentionally lightweight and does not claim automatic WCAG compliance.

Compatibility relies first on Filament's stable color, font, and sidebar variables. The few direct component hooks are centralized in `CssCompiler`: `.fi-body`, `.fi-sidebar`, `.fi-main`, `.fi-simple-main`, `.fi-section`, `.fi-ta-ctn`, `.fi-modal-window`, `.fi-btn`, `.fi-input-wrp`, `.fi-input`, `.fi-badge`, and `.fi-dropdown-panel`. These hooks exist in both Filament 4 and 5 but may need adaptation for a future major release.

Every structured value is reconstructed from validated allowlists or bounded numeric values. Invalid legacy database settings cause an atomic fallback to canonical defaults.

## Custom CSS for trusted administrators

Custom CSS is disabled by default and requires both general Theme Studio access and the separate custom-CSS authorization. It is not a security boundary against a trusted administrator: CSS can still hide controls, imitate interface elements, or make a panel difficult to use. Grant this permission only to trusted administrators.

Enable it explicitly in the published configuration:

```php
'custom_css' => [
    'enabled' => true,
    'max_bytes' => 50_000,
    'mode' => 'strict',
    'allow_on_auth_pages' => false,
    'allow_external_urls' => false,
    'allowed_media_queries' => true,
],
```

The editor uses a native textarea and explicit validation, preview, publication, disable, and delete actions. Preview CSS is validated by the same service as published CSS, stored for ten minutes under a cryptographically random token, bound to the authenticated user, panel, and theme, and displayed on a controlled page with a restrictive CSP. Previewing never changes the database, active theme, or public CSS cache.

Publication validates before starting a transaction, creates a snapshot, stores the editable source, updates its independent activation flag, and invalidates panel caches only after commit. The compiler validates the source again and appends only the normalized output after structured CSS. Invalid CSS inserted directly in the database is logged without its contents and omitted. Restoring an old snapshot restores its source, but leaves it disabled if current rules reject it.

Strict mode allows visual properties such as colors, backgrounds, borders, shadows, opacity, typography, spacing, dimensions, and overflow. Safe functions are `rgb()`, `rgba()`, `hsl()`, `hsla()`, `calc()`, `min()`, `max()`, `clamp()`, and `var()`; variables referenced by `var()` must use `--fts-`, while declarations of new variables must use `--fts-custom-`. Selectors must target Filament `.fi-*` elements and are scoped below `.fi-body`.

URLs, external resources, HTML, control characters, `@import`, `@namespace`, `@font-face`, `@document`, `@keyframes`, `behavior`, `-moz-binding`, `expression()`, script schemes, overlay-oriented properties, pseudo-elements, authentication selectors, hidden fields, and token selectors are rejected. Only recursively validated `@media`, `@supports`, and `@layer` blocks are accepted. Custom CSS is excluded from Filament authentication pages by default.

If custom CSS makes the interface unusable, recover without Filament:

```bash
php artisan filament-theme-studio:disable-custom-css --panel=admin
php artisan filament-theme-studio:disable-custom-css --theme=1
php artisan filament-theme-studio:disable-custom-css --all --force
```

Exactly one target is required. Production asks for confirmation unless `--force` is supplied. This command disables only custom CSS; structured theme settings remain active.

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

    'css' => [
        'enabled' => true,
        'route_prefix' => 'filament-theme-studio/styles',
        'cache_control' => 'public, max-age=3600',
        'compiler_version' => '1',
    ],

    'custom_css' => [
        'enabled' => false,
        'max_bytes' => 50_000,
        'mode' => 'strict',
        'allow_on_auth_pages' => false,
        'allow_external_urls' => false,
        'allowed_media_queries' => true,
    ],
];
```

The active theme cache stores only a theme identifier under a deterministic, panel-specific key such as `filament-theme-studio:panel:admin:active-theme`; the model is reloaded on every read. Compiled CSS uses a separate key containing compiler version, panel, theme, and normalized-settings fingerprint, and its value is a scalar array rather than a serialized package object. Invalid or legacy entries are discarded and rebuilt automatically, including after a package upgrade. A `null` store uses Laravel's default cache store, a TTL of `0` caches forever, and `enabled => false` bypasses both caches. Successful update, activation, deactivation, restoration, and deletion operations invalidate only the affected panel after database writes complete.

Set `css.enabled` to `false` to disable both the stylesheet link and endpoint. `route_prefix` accepts only local path segments, and `compiler_version` can be incremented when a future compiler format must invalidate old entries.

`custom_css` retains the editable source while only the current parser's normalized output is served. The cache fingerprint includes the source, activation state, validator version, strict-mode options, and compiler version. Cached values remain scalar arrays. Remote fonts are never loaded, and no Node, Vite, Tailwind, or PostCSS process runs at request time.

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
