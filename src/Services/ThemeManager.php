<?php

declare(strict_types=1);

namespace Hforlife\FilamentThemeStudio\Services;

use Hforlife\FilamentThemeStudio\Exceptions\DuplicateThemeSlug;
use Hforlife\FilamentThemeStudio\Exceptions\InvalidCustomCss;
use Hforlife\FilamentThemeStudio\Exceptions\InvalidThemeSettings;
use Hforlife\FilamentThemeStudio\Exceptions\ThemeVersionNotFound;
use Hforlife\FilamentThemeStudio\Models\Theme;
use Hforlife\FilamentThemeStudio\Models\ThemeVersion;
use Hforlife\FilamentThemeStudio\Support\CustomCssConfiguration;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ThemeManager
{
    public function __construct(
        private readonly ConnectionInterface $database,
        private readonly ThemeCache $cache,
        private readonly CompiledCssCache $compiledCss,
        private readonly CustomCssValidator $customCssValidator,
        private readonly CustomCssPreview $customCssPreview,
    ) {}

    /** @param array<string, mixed> $settings */
    public function createTheme(
        string $panelId,
        string $name,
        array $settings = [],
        ?string $customCss = null,
        ?string $slug = null,
        int | string | null $createdBy = null,
        bool $customCssEnabled = false,
    ): Theme {
        $panelId = $this->required($panelId, 'Panel ID');
        $name = $this->required($name, 'Theme name');
        $slug = $this->normaliseSlug($slug ?? $name);

        if (Theme::query()->where('panel_id', $panelId)->where('slug', $slug)->exists()) {
            throw DuplicateThemeSlug::forPanel();
        }

        try {
            $theme = Theme::query()->create([
                'panel_id' => $panelId,
                'name' => $name,
                'slug' => $slug,
                'settings' => $settings,
                'custom_css' => $customCss,
                'custom_css_enabled' => $customCssEnabled,
                'is_active' => false,
                'created_by' => $this->actorId($createdBy),
                'updated_by' => $this->actorId($createdBy),
            ]);
        } catch (QueryException $exception) {
            if ($this->isUniqueConstraintViolation($exception)) {
                throw DuplicateThemeSlug::forPanel();
            }

            throw $exception;
        }

        $this->forgetPanelCaches($panelId);

        return $theme;
    }

    /** @param array{name?: string, slug?: string, settings?: array<string, mixed>, custom_css?: ?string} $attributes */
    public function updateTheme(Theme $theme, array $attributes, int | string | null $updatedBy = null): Theme
    {
        $theme = $this->freshTheme($theme);
        $allowed = array_intersect_key($attributes, array_flip(['name', 'slug', 'settings', 'custom_css']));

        if (array_key_exists('name', $allowed)) {
            $allowed['name'] = $this->required((string) $allowed['name'], 'Theme name');
        }

        if (array_key_exists('slug', $allowed)) {
            $allowed['slug'] = $this->normaliseSlug((string) $allowed['slug']);

            $duplicate = Theme::query()
                ->where('panel_id', $theme->panel_id)
                ->where('slug', $allowed['slug'])
                ->whereKeyNot($theme->getKey())
                ->exists();

            if ($duplicate) {
                throw DuplicateThemeSlug::forPanel();
            }
        }

        if ($updatedBy !== null) {
            $allowed['updated_by'] = $this->actorId($updatedBy);
        }

        try {
            $theme->update($allowed);
        } catch (QueryException $exception) {
            if ($this->isUniqueConstraintViolation($exception)) {
                throw DuplicateThemeSlug::forPanel();
            }

            throw $exception;
        }

        $this->forgetPanelCaches($theme->panel_id);

        return $theme->refresh();
    }

    public function duplicateTheme(
        Theme $theme,
        string $name,
        ?string $slug = null,
        int | string | null $createdBy = null,
    ): Theme {
        $theme = $this->freshTheme($theme);

        return $this->createTheme(
            panelId: $theme->panel_id,
            name: $name,
            settings: $theme->settings,
            customCss: $theme->custom_css,
            slug: $slug,
            createdBy: $createdBy,
            customCssEnabled: $theme->custom_css_enabled,
        );
    }

    public function publishCustomCss(
        Theme $theme,
        string $css,
        bool $enabled = true,
        int | string | null $updatedBy = null,
    ): Theme {
        if (! CustomCssConfiguration::enabled()) {
            throw new InvalidCustomCss('Custom CSS is disabled by configuration.');
        }

        $validation = $this->customCssValidator->validate($css);
        if (! $validation->valid) {
            throw InvalidCustomCss::fromResult($validation);
        }

        $panelId = $theme->panel_id;
        $published = $this->database->transaction(function () use ($theme, $css, $enabled, $updatedBy): Theme {
            $lockedTheme = $this->lockTheme($theme);
            $this->createVersionSnapshot($lockedTheme, __('filament-theme-studio::theme-studio.version_notes.before_custom_css_publish'), $updatedBy);
            $lockedTheme->update([
                'custom_css' => $css,
                'custom_css_enabled' => $enabled,
                'updated_by' => $updatedBy === null ? $lockedTheme->updated_by : $this->actorId($updatedBy),
            ]);
            $this->pruneVersions($lockedTheme);

            return $lockedTheme->refresh();
        }, 3);

        $this->forgetPanelCaches($panelId);
        $this->customCssPreview->forgetAllForTheme($published);

        return $published;
    }

    public function setCustomCssEnabled(Theme $theme, bool $enabled, int | string | null $updatedBy = null): Theme
    {
        $theme = $this->freshTheme($theme);

        if ($enabled) {
            $validation = $this->customCssValidator->validate((string) $theme->custom_css);
            if (! CustomCssConfiguration::enabled() || ! $validation->valid) {
                throw InvalidCustomCss::fromResult($validation);
            }
        }

        return $this->publishCustomCssState($theme, $enabled, $updatedBy);
    }

    public function deleteCustomCss(Theme $theme, int | string | null $updatedBy = null): Theme
    {
        $theme = $this->freshTheme($theme);
        $panelId = $theme->panel_id;
        $deleted = $this->database->transaction(function () use ($theme, $updatedBy): Theme {
            $lockedTheme = $this->lockTheme($theme);
            $this->createVersionSnapshot($lockedTheme, __('filament-theme-studio::theme-studio.version_notes.before_custom_css_delete'), $updatedBy);
            $lockedTheme->update([
                'custom_css' => null,
                'custom_css_enabled' => false,
                'updated_by' => $updatedBy === null ? $lockedTheme->updated_by : $this->actorId($updatedBy),
            ]);
            $this->pruneVersions($lockedTheme);

            return $lockedTheme->refresh();
        }, 3);
        $this->forgetPanelCaches($panelId);
        $this->customCssPreview->forgetAllForTheme($deleted);

        return $deleted;
    }

    public function activateTheme(Theme $theme, int | string | null $updatedBy = null): Theme
    {
        $panelId = $theme->panel_id;

        $activated = $this->database->transaction(function () use ($theme, $updatedBy): Theme {
            $currentTheme = $this->freshTheme($theme);
            $this->lockPanel($currentTheme->panel_id);
            $lockedTheme = $this->freshTheme($theme);

            Theme::query()
                ->where('panel_id', $lockedTheme->panel_id)
                ->where('is_active', true)
                ->whereKeyNot($lockedTheme->getKey())
                ->lockForUpdate()
                ->update(['is_active' => false]);

            $attributes = ['is_active' => true];

            if ($updatedBy !== null) {
                $attributes['updated_by'] = $this->actorId($updatedBy);
            }

            $lockedTheme->update($attributes);

            return $lockedTheme->refresh();
        }, 3);

        $this->forgetPanelCaches($panelId);

        return $activated;
    }

    public function deactivateTheme(string $panelId, int | string | null $updatedBy = null): void
    {
        $panelId = $this->required($panelId, 'Panel ID');

        $this->database->transaction(function () use ($panelId, $updatedBy): void {
            $attributes = ['is_active' => false];

            if ($updatedBy !== null) {
                $attributes['updated_by'] = $this->actorId($updatedBy);
            }

            Theme::query()
                ->where('panel_id', $panelId)
                ->where('is_active', true)
                ->lockForUpdate()
                ->update($attributes);
        }, 3);

        $this->forgetPanelCaches($panelId);
    }

    public function createVersion(
        Theme $theme,
        ?string $changeNote = null,
        int | string | null $createdBy = null,
    ): ThemeVersion {
        return $this->database->transaction(function () use ($theme, $changeNote, $createdBy): ThemeVersion {
            $lockedTheme = $this->lockTheme($theme);
            $version = $this->createVersionSnapshot($lockedTheme, $changeNote, $createdBy);
            $this->pruneVersions($lockedTheme);

            return $version;
        }, 3);
    }

    public function restoreVersion(
        Theme $theme,
        ThemeVersion $version,
        int | string | null $updatedBy = null,
    ): Theme {
        $panelId = $theme->panel_id;

        $restored = $this->database->transaction(function () use ($theme, $version, $updatedBy): Theme {
            $lockedTheme = $this->lockTheme($theme);
            $snapshot = ThemeVersion::query()->find($version->getKey());

            if ($snapshot === null || (int) $snapshot->theme_id !== (int) $lockedTheme->getKey()) {
                throw ThemeVersionNotFound::forTheme();
            }

            $backup = $this->createVersionSnapshot($lockedTheme, 'Automatic backup before restore', $updatedBy);

            $attributes = [
                'settings' => $snapshot->settings,
                'custom_css' => $snapshot->custom_css,
                'custom_css_enabled' => $snapshot->custom_css_enabled
                    && CustomCssConfiguration::enabled()
                    && $this->customCssValidator->validate((string) $snapshot->custom_css)->valid,
            ];

            if ($updatedBy !== null) {
                $attributes['updated_by'] = $this->actorId($updatedBy);
            }

            $lockedTheme->update($attributes);
            $this->pruneVersions($lockedTheme, [
                (int) $snapshot->getKey(),
                (int) $backup->getKey(),
            ]);

            return $lockedTheme->refresh();
        }, 3);

        $this->forgetPanelCaches($panelId);
        $this->customCssPreview->forgetAllForTheme($restored);

        return $restored;
    }

    public function getActiveTheme(string $panelId): ?Theme
    {
        return $this->cache->getActiveTheme($this->required($panelId, 'Panel ID'));
    }

    public function deleteTheme(Theme $theme): void
    {
        $theme = $this->freshTheme($theme);
        $panelId = $theme->panel_id;

        $this->database->transaction(fn (): ?bool => $theme->delete(), 3);
        $this->forgetPanelCaches($panelId);
    }

    private function createVersionSnapshot(
        Theme $theme,
        ?string $changeNote,
        int | string | null $createdBy,
    ): ThemeVersion {
        $latestVersion = (int) $theme->versions()->max('version');

        return $theme->versions()->create([
            'version' => $latestVersion + 1,
            'settings' => $theme->settings,
            'custom_css' => $theme->custom_css,
            'custom_css_enabled' => $theme->custom_css_enabled,
            'change_note' => $changeNote,
            'created_by' => $this->actorId($createdBy),
        ]);
    }

    private function forgetPanelCaches(string $panelId): void
    {
        $this->cache->forget($panelId);
        $this->compiledCss->forgetPanel($panelId);
    }

    private function publishCustomCssState(Theme $theme, bool $enabled, int | string | null $updatedBy): Theme
    {
        $panelId = $theme->panel_id;
        $updated = $this->database->transaction(function () use ($theme, $enabled, $updatedBy): Theme {
            $lockedTheme = $this->lockTheme($theme);
            $this->createVersionSnapshot($lockedTheme, __('filament-theme-studio::theme-studio.version_notes.before_custom_css_state'), $updatedBy);
            $attributes = ['custom_css_enabled' => $enabled];
            if ($updatedBy !== null) {
                $attributes['updated_by'] = $this->actorId($updatedBy);
            }
            $lockedTheme->update($attributes);
            $this->pruneVersions($lockedTheme);

            return $lockedTheme->refresh();
        }, 3);
        $this->forgetPanelCaches($panelId);
        $this->customCssPreview->forgetAllForTheme($updated);

        return $updated;
    }

    /** @param array<int> $preserveVersionIds */
    private function pruneVersions(Theme $theme, array $preserveVersionIds = []): void
    {
        $limit = config('filament-theme-studio.versions.limit', 20);

        if (! is_int($limit) || $limit < 1) {
            throw InvalidThemeSettings::invalidVersionLimit();
        }

        $effectiveLimit = max($limit, count($preserveVersionIds));
        $query = $theme->versions()->oldest('version');

        if ($preserveVersionIds !== []) {
            $query->whereNotIn($theme->versions()->getRelated()->getQualifiedKeyName(), $preserveVersionIds);
        }

        $excess = max(0, $theme->versions()->count() - $effectiveLimit);

        if ($excess > 0) {
            $query->limit($excess)->delete();
        }
    }

    private function freshTheme(Theme $theme): Theme
    {
        return Theme::query()->findOrFail($theme->getKey());
    }

    private function lockTheme(Theme $theme): Theme
    {
        $this->database
            ->table($theme->getTable())
            ->where($theme->getKeyName(), $theme->getKey())
            ->lockForUpdate()
            ->first();

        return $this->freshTheme($theme);
    }

    private function lockPanel(string $panelId): void
    {
        $this->database
            ->table((new Theme)->getTable())
            ->where('panel_id', $panelId)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    private function required(string $value, string $field): string
    {
        $value = trim($value);

        if ($value === '') {
            throw new InvalidArgumentException("{$field} cannot be empty.");
        }

        return $value;
    }

    private function normaliseSlug(string $slug): string
    {
        $slug = Str::slug($slug);

        if ($slug === '') {
            throw new InvalidArgumentException('Theme slug cannot be empty.');
        }

        return $slug;
    }

    private function actorId(int | string | null $actor): ?string
    {
        return $actor === null ? null : (string) $actor;
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        return in_array((string) $exception->getCode(), ['23000', '23505'], true);
    }
}
