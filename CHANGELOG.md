# Changelog

All notable changes to `hforlife/filament-theme-studio` will be documented in this file.

## Unreleased

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
