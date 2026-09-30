<?php

declare(strict_types=1);

namespace Hforlife\FilamentThemeStudio\Resources\ThemeResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Hforlife\FilamentThemeStudio\Exceptions\DuplicateThemeSlug;
use Hforlife\FilamentThemeStudio\Resources\ThemeResource;
use Hforlife\FilamentThemeStudio\Services\ThemeManager;
use Hforlife\FilamentThemeStudio\Support\ThemeSettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CreateTheme extends CreateRecord
{
    protected static string $resource = ThemeResource::class;

    /** @return array<string, mixed> */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['settings'] = ThemeSettings::normalize($data['settings'] ?? []);

        return $data;
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            $theme = app(ThemeManager::class)->createTheme(
                panelId: ThemeResource::currentPanelId(),
                name: (string) $data['name'],
                settings: $data['settings'],
                slug: (string) $data['slug'],
                createdBy: $this->actorId(),
            );
        } catch (DuplicateThemeSlug) {
            throw ValidationException::withMessages([
                'data.slug' => __('filament-theme-studio::theme-studio.errors.duplicate_slug'),
            ]);
        }

        if ((bool) ($data['activate_after_create'] ?? false)) {
            app(ThemeManager::class)->activateTheme($theme, $this->actorId());
        }

        return $theme;
    }

    protected function getRedirectUrl(): string
    {
        return ThemeResource::getUrl('edit', ['record' => $this->getRecord()]);
    }

    private function actorId(): int | string | null
    {
        $id = Auth::id();

        return is_int($id) || is_string($id) ? $id : null;
    }
}
