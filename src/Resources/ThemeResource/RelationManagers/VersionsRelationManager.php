<?php

declare(strict_types=1);

namespace Hforlife\FilamentThemeStudio\Resources\ThemeResource\RelationManagers;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Hforlife\FilamentThemeStudio\Models\Theme;
use Hforlife\FilamentThemeStudio\Models\ThemeVersion;
use Hforlife\FilamentThemeStudio\Resources\ThemeResource;
use Hforlife\FilamentThemeStudio\Services\ThemeManager;
use Illuminate\Support\Facades\Auth;

class VersionsRelationManager extends RelationManager
{
    protected static string $relationship = 'versions';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('filament-theme-studio::theme-studio.versions.heading'))
            ->defaultSort('version', 'desc')
            ->columns([
                TextColumn::make('version')->label(__('filament-theme-studio::theme-studio.fields.version'))->sortable(),
                TextColumn::make('created_at')->label(__('filament-theme-studio::theme-studio.fields.created_at'))->dateTime()->sortable(),
                TextColumn::make('created_by')->label(__('filament-theme-studio::theme-studio.fields.created_by'))->placeholder('—'),
                TextColumn::make('change_note')->label(__('filament-theme-studio::theme-studio.fields.change_note'))->placeholder('—')->wrap(),
            ])
            ->recordActions([
                Action::make('restore')
                    ->label(__('filament-theme-studio::theme-studio.actions.restore'))
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->modalDescription(__('filament-theme-studio::theme-studio.confirmations.restore'))
                    ->action(function (ThemeVersion $record): void {
                        $owner = $this->getOwnerRecord();
                        abort_unless($owner instanceof Theme, 404);
                        ThemeResource::authorizeRecord($owner);
                        abort_unless((int) $record->theme_id === (int) $owner->getKey(), 403);
                        if (filled($record->custom_css) || $record->custom_css_enabled) {
                            ThemeResource::authorizeCustomCss($owner);
                        }

                        $id = Auth::id();
                        $actor = is_int($id) || is_string($id) ? $id : null;
                        $restored = app(ThemeManager::class)->restoreVersion($owner, $record, $actor);
                        $notification = $record->custom_css_enabled && ! $restored->custom_css_enabled
                            ? 'restored_custom_css_disabled'
                            : 'restored';

                        Notification::make()->success()
                            ->title(__("filament-theme-studio::theme-studio.notifications.{$notification}"))
                            ->send();

                        $this->redirect(ThemeResource::getUrl('edit', ['record' => $owner]));
                    }),
            ]);
    }
}
