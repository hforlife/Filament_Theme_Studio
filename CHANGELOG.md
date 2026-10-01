# Changelog

All notable changes to `hforlife/filament-theme-studio` will be documented in this file.

## Unreleased

## 0.4.0 - 2026-10-01

- Add deterministic, validated CSS compilation from the canonical structured theme settings.
- Add local color palette generation and Filament-compatible semantic color variables.
- Load a versioned external stylesheet only for active themes on panels that registered the plugin.
- Add a panel-isolated CSS route with strict identifiers, MIME and `nosniff` headers, HTTP caching, ETags, and 304 responses.
- Add a separate compiled CSS cache with compiler-versioned keys and post-transaction invalidation.
- Store compiled CSS cache entries as validated scalar arrays so stale serialized package objects are safely discarded and rebuilt after upgrades.
- Add safe fallback behavior for corrupt legacy settings and exclude `custom_css` completely.
- Add compiler, injection resistance, cache, HTTP, panel-link, ETag, and Filament 4/5 compatibility tests.

## 0.3.0 - 2026-09-30

- Add the panel-scoped Filament Theme resource with native list, create, and edit pages.
- Add canonical theme controls and server-side validation for palettes, typography, shape, sidebar, width, and density.
- Add activation, deactivation, duplication, deletion, snapshots, and version restoration to the administration interface.
- Add panel-specific navigation configuration and callback-based authorization, with a `manage-theme-studio` ability fallback.
- Add English and French interface translations.
- Add authorization, panel isolation, validation, registration, and translation tests for Filament 4 and Filament 5.
- Keep CSS generation, preview, and runtime injection outside this release.

## 0.2.0 - 2026-09-30

- Add portable, publishable theme and immutable version migrations.
- Add theme models, factories, panel-scoped queries, and cascade deletion.
- Add the transactional `ThemeManager` API for creation, updates, duplication, activation, versioning, restoration, and deletion.
- Add configurable, panel-isolated active-theme caching and invalidation.
- Add storage, transaction, cache, and version-retention tests.
