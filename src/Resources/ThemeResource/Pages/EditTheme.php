<?php

declare(strict_types=1);

namespace Hforlife\FilamentThemeStudio\Resources\ThemeResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Hforlife\FilamentThemeStudio\Exceptions\DuplicateThemeSlug;
use Hforlife\FilamentThemeStudio\Models\Theme;
use Hforlife\FilamentThemeStudio\Resources\ThemeResource;
use Hforlife\FilamentThemeStudio\Services\ThemeManager;
use Hforlife\FilamentThemeStudio\Support\ThemeSettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EditTheme extends EditRecord
{
    protected static string $resource = ThemeResource::class;

    /** @param array<string, mixed> $data @return array<string, mixed> */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['settings'] = ThemeSettings::normalize($data['settings'] ?? []);

        return $data;
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $theme = $this->theme();
        $data['settings'] = ThemeSettings::normalize($data['settings'] ?? [], $theme->settings);

        return $data;
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $theme = $this->theme($record);
        ThemeResource::authorizeRecord($theme);
        $manager = app(ThemeManager::class);
        $manager->createVersion($theme, __('filament-theme-studio::theme-studio.version_notes.before_update'), $this->actorId());

        try {
            return $manager->updateTheme($theme, [
                'name' => (string) $data['name'],
                'slug' => (string) $data['slug'],
                'settings' => $data['settings'],
            ], $this->actorId());
        } catch (DuplicateThemeSlug) {
            throw ValidationException::withMessages([
                'data.slug' => __('filament-theme-studio::theme-studio.errors.duplicate_slug'),
            ]);
        }
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('activate')
                ->label(__('filament-theme-studio::theme-studio.actions.activate'))
                ->requiresConfirmation()
                ->visible(fn (): bool => ! $this->theme()->is_active)
                ->action(function (): void {
                    $theme = $this->theme();
                    ThemeResource::authorizeRecord($theme);
                    app(ThemeManager::class)->activateTheme($theme, $this->actorId());
                    $this->record = $theme->refresh();
                    $this->notify('activated');
                }),
            Action::make('deactivate')
                ->label(__('filament-theme-studio::theme-studio.actions.deactivate'))
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->theme()->is_active)
                ->action(function (): void {
                    ThemeResource::authorizeRecord($this->theme());
                    app(ThemeManager::class)->deactivateTheme(ThemeResource::currentPanelId(), $this->actorId());
                    $this->record = $this->theme()->refresh();
                    $this->notify('deactivated');
                }),
            DeleteAction::make()
                ->modalDescription(__('filament-theme-studio::theme-studio.confirmations.delete'))
                ->using(function (Model $record): bool {
                    $theme = $this->theme($record);
                    ThemeResource::authorizeRecord($theme);
                    app(ThemeManager::class)->deleteTheme($theme);

                    return true;
                }),
        ];
    }

    private function theme(?Model $record = null): Theme
    {
        $record ??= $this->getRecord();
        if (! $record instanceof Theme) {
            throw new NotFoundHttpException;
        }

        return $record;
    }

    private function actorId(): int | string | null
    {
        $id = Auth::id();

        return is_int($id) || is_string($id) ? $id : null;
    }

    private function notify(string $key): void
    {
        Notification::make()->success()->title(__("filament-theme-studio::theme-studio.notifications.{$key}"))->send();
    }
}
