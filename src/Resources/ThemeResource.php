<?php

declare(strict_types=1);

namespace Hforlife\FilamentThemeStudio\Resources;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Hforlife\FilamentThemeStudio\FilamentThemeStudioPlugin;
use Hforlife\FilamentThemeStudio\Models\Theme;
use Hforlife\FilamentThemeStudio\Resources\ThemeResource\Pages\CreateTheme;
use Hforlife\FilamentThemeStudio\Resources\ThemeResource\Pages\EditTheme;
use Hforlife\FilamentThemeStudio\Resources\ThemeResource\Pages\ListThemes;
use Hforlife\FilamentThemeStudio\Resources\ThemeResource\RelationManagers\VersionsRelationManager;
use Hforlife\FilamentThemeStudio\Services\ThemeManager;
use Hforlife\FilamentThemeStudio\Support\CustomCssConfiguration;
use Hforlife\FilamentThemeStudio\Support\ThemeSettings;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** @extends resource<Theme> */
class ThemeResource extends Resource
{
    protected static ?string $model = Theme::class;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('filament-theme-studio::theme-studio.sections.identity'))
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label(__('filament-theme-studio::theme-studio.fields.name'))
                        ->required()
                        ->maxLength(120),
                    TextInput::make('slug')
                        ->label(__('filament-theme-studio::theme-studio.fields.slug'))
                        ->required()
                        ->maxLength(120)
                        ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/'),
                    Toggle::make('activate_after_create')
                        ->label(__('filament-theme-studio::theme-studio.fields.activate_after_create'))
                        ->visible(fn (?Model $record): bool => ! $record instanceof Model),
                ]),
            Section::make(__('filament-theme-studio::theme-studio.sections.colors'))
                ->columns(4)
                ->schema(self::colorFields('settings.colors', [
                    'primary', 'secondary', 'success', 'warning', 'danger', 'background', 'surface', 'text',
                ])),
            Section::make(__('filament-theme-studio::theme-studio.sections.sidebar'))
                ->columns(3)
                ->schema([
                    ...self::colorFields('settings.colors', ['sidebar_background', 'sidebar_text']),
                    TextInput::make('settings.layout.sidebar_width')
                        ->label(__('filament-theme-studio::theme-studio.fields.sidebar_width'))
                        ->numeric()->required()->minValue(200)->maxValue(420)->suffix('px'),
                ]),
            Section::make(__('filament-theme-studio::theme-studio.sections.dark_mode'))
                ->columns(3)
                ->schema(self::colorFields('settings.dark_colors', [
                    'background', 'surface', 'text', 'sidebar_background', 'sidebar_text',
                ])),
            Section::make(__('filament-theme-studio::theme-studio.sections.typography'))
                ->columns(2)
                ->schema([
                    Select::make('settings.typography.font_family')
                        ->label(__('filament-theme-studio::theme-studio.fields.font_family'))
                        ->options(array_combine(ThemeSettings::fontFamilies(), ThemeSettings::fontFamilies()))
                        ->required(),
                    TextInput::make('settings.typography.base_size')
                        ->label(__('filament-theme-studio::theme-studio.fields.base_size'))
                        ->numeric()->required()->minValue(12)->maxValue(24)->suffix('px'),
                ]),
            Section::make(__('filament-theme-studio::theme-studio.sections.shape'))
                ->columns(4)
                ->schema([
                    self::radiusField('border_radius'),
                    self::radiusField('button_radius'),
                    self::radiusField('input_radius'),
                    self::radiusField('card_radius'),
                ]),
            Section::make(__('filament-theme-studio::theme-studio.sections.layout'))
                ->columns(2)
                ->schema([
                    Select::make('settings.layout.content_max_width')
                        ->label(__('filament-theme-studio::theme-studio.fields.content_max_width'))
                        ->options([
                            'full' => __('filament-theme-studio::theme-studio.options.full'),
                            'screen-xl' => 'XL', 'screen-2xl' => '2XL', 'screen-3xl' => '3XL',
                            'screen-4xl' => '4XL', 'screen-5xl' => '5XL', 'screen-6xl' => '6XL', 'screen-7xl' => '7XL',
                        ])->required(),
                    Select::make('settings.layout.density')
                        ->label(__('filament-theme-studio::theme-studio.fields.density'))
                        ->options([
                            'compact' => __('filament-theme-studio::theme-studio.options.compact'),
                            'comfortable' => __('filament-theme-studio::theme-studio.options.comfortable'),
                            'spacious' => __('filament-theme-studio::theme-studio.options.spacious'),
                        ])->required(),
                ]),
            Section::make(__('filament-theme-studio::theme-studio.custom_css.title'))
                ->description(__('filament-theme-studio::theme-studio.custom_css.warning'))
                ->visible(fn (): bool => self::canManageCustomCss())
                ->schema([
                    Textarea::make('custom_css')
                        ->label(__('filament-theme-studio::theme-studio.custom_css.editor'))
                        ->rows(18)
                        ->maxLength(CustomCssConfiguration::maxBytes())
                        ->helperText(__('filament-theme-studio::theme-studio.custom_css.help', [
                            'max' => CustomCssConfiguration::maxBytes(),
                        ])),
                    Toggle::make('custom_css_enabled')
                        ->label(__('filament-theme-studio::theme-studio.custom_css.enabled')),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('filament-theme-studio::theme-studio.fields.name'))->searchable()->sortable(),
                TextColumn::make('slug')->label(__('filament-theme-studio::theme-studio.fields.slug'))->searchable()->sortable(),
                TextColumn::make('panel_id')->label(__('filament-theme-studio::theme-studio.fields.panel')),
                TextColumn::make('is_active')
                    ->label(__('filament-theme-studio::theme-studio.fields.status'))
                    ->formatStateUsing(fn (bool $state): string => __(
                        'filament-theme-studio::theme-studio.badges.' . ($state ? 'active' : 'inactive'),
                    ))
                    ->badge()
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                TextColumn::make('versions_count')->label(__('filament-theme-studio::theme-studio.fields.versions'))->counts('versions')->sortable(),
                TextColumn::make('updated_by')->label(__('filament-theme-studio::theme-studio.fields.updated_by'))->placeholder('—'),
                TextColumn::make('updated_at')->label(__('filament-theme-studio::theme-studio.fields.updated_at'))->dateTime()->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label(__('filament-theme-studio::theme-studio.filters.active')),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('activate')
                    ->label(__('filament-theme-studio::theme-studio.actions.activate'))
                    ->icon('heroicon-o-check-circle')
                    ->requiresConfirmation()
                    ->visible(fn (Theme $record): bool => ! $record->is_active)
                    ->action(function (Theme $record): void {
                        self::authorizeRecord($record);
                        app(ThemeManager::class)->activateTheme($record, self::actorId());
                        self::success('activated');
                    }),
                Action::make('deactivate')
                    ->label(__('filament-theme-studio::theme-studio.actions.deactivate'))
                    ->icon('heroicon-o-x-circle')
                    ->requiresConfirmation()
                    ->visible(fn (Theme $record): bool => $record->is_active)
                    ->action(function (Theme $record): void {
                        self::authorizeRecord($record);
                        app(ThemeManager::class)->deactivateTheme(self::currentPanelId(), self::actorId());
                        self::success('deactivated');
                    }),
                Action::make('snapshot')
                    ->label(__('filament-theme-studio::theme-studio.actions.snapshot'))
                    ->icon('heroicon-o-camera')
                    ->schema([
                        TextInput::make('change_note')->label(__('filament-theme-studio::theme-studio.fields.change_note'))->maxLength(500),
                    ])
                    ->action(function (Theme $record, array $data): void {
                        self::authorizeRecord($record);
                        app(ThemeManager::class)->createVersion($record, $data['change_note'] ?? null, self::actorId());
                        self::success('snapshot_created');
                    }),
                Action::make('duplicate')
                    ->label(__('filament-theme-studio::theme-studio.actions.duplicate'))
                    ->icon('heroicon-o-document-duplicate')
                    ->schema([
                        TextInput::make('name')->label(__('filament-theme-studio::theme-studio.fields.name'))->required()->maxLength(120),
                        TextInput::make('slug')->label(__('filament-theme-studio::theme-studio.fields.slug'))->required()->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/'),
                    ])
                    ->action(function (Theme $record, array $data): void {
                        self::authorizeRecord($record);
                        if (filled($record->custom_css) || $record->custom_css_enabled) {
                            self::authorizeCustomCss($record);
                        }
                        app(ThemeManager::class)->duplicateTheme($record, (string) $data['name'], (string) $data['slug'], self::actorId());
                        self::success('duplicated');
                    }),
                Action::make('delete')
                    ->label(__('filament-theme-studio::theme-studio.actions.delete'))
                    ->icon('heroicon-o-trash')->color('danger')->requiresConfirmation()
                    ->modalDescription(__('filament-theme-studio::theme-studio.confirmations.delete'))
                    ->action(function (Theme $record): void {
                        self::authorizeRecord($record);
                        app(ThemeManager::class)->deleteTheme($record);
                        self::success('deleted');
                    }),
            ])
            ->emptyStateHeading(__('filament-theme-studio::theme-studio.empty.heading'))
            ->emptyStateDescription(__('filament-theme-studio::theme-studio.empty.description'))
            ->emptyStateActions([
                CreateAction::make(),
            ]);
    }

    /** @return array<class-string> */
    public static function getRelations(): array
    {
        return [VersionsRelationManager::class];
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ListThemes::route('/'),
            'create' => CreateTheme::route('/create'),
            'edit' => EditTheme::route('/{record}/edit'),
        ];
    }

    /** @return Builder<Theme> */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('panel_id', self::currentPanelId());
    }

    public static function canAccess(): bool
    {
        return self::plugin()->isAuthorized();
    }

    public static function canViewAny(): bool
    {
        return self::canAccess();
    }

    public static function canCreate(): bool
    {
        return self::canAccess();
    }

    public static function canEdit(Model $record): bool
    {
        return self::canAccess() && self::belongsToCurrentPanel($record);
    }

    public static function canView(Model $record): bool
    {
        return self::canEdit($record);
    }

    public static function canDelete(Model $record): bool
    {
        return self::canEdit($record);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return self::plugin()->shouldShowInNavigation() && self::canAccess();
    }

    public static function getNavigationLabel(): string
    {
        return self::plugin()->getNavigationLabel();
    }

    public static function getNavigationGroup(): string
    {
        return self::plugin()->getNavigationGroup();
    }

    public static function getNavigationSort(): ?int
    {
        return self::plugin()->getNavigationSort();
    }

    public static function getNavigationIcon(): string | BackedEnum | Htmlable | null
    {
        return 'heroicon-o-paint-brush';
    }

    public static function getModelLabel(): string
    {
        return __('filament-theme-studio::theme-studio.model.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-theme-studio::theme-studio.model.plural');
    }

    public static function currentPanelId(): string
    {
        $panel = Filament::getCurrentOrDefaultPanel();
        abort_if($panel === null, 404);

        return $panel->getId();
    }

    public static function authorizeRecord(Theme $theme): void
    {
        abort_unless(self::canEdit($theme), 403);
    }

    public static function canManageCustomCss(): bool
    {
        return CustomCssConfiguration::enabled() && self::plugin()->isCustomCssAuthorized();
    }

    public static function authorizeCustomCss(Theme $theme): void
    {
        self::authorizeRecord($theme);
        abort_unless(self::canManageCustomCss(), 403);
    }

    private static function belongsToCurrentPanel(Model $record): bool
    {
        return $record instanceof Theme && $record->panel_id === self::currentPanelId();
    }

    private static function plugin(): FilamentThemeStudioPlugin
    {
        $plugin = Filament::getCurrentOrDefaultPanel()?->getPlugin('filament-theme-studio');

        if (! $plugin instanceof FilamentThemeStudioPlugin) {
            throw new NotFoundHttpException;
        }

        return $plugin;
    }

    private static function actorId(): int | string | null
    {
        $id = Auth::id();

        return is_int($id) || is_string($id) ? $id : null;
    }

    private static function success(string $key): void
    {
        Notification::make()->success()->title(__("filament-theme-studio::theme-studio.notifications.{$key}"))->send();
    }

    /** @param array<string> $names @return array<ColorPicker> */
    private static function colorFields(string $prefix, array $names): array
    {
        return array_map(
            fn (string $name): ColorPicker => ColorPicker::make("{$prefix}.{$name}")
                ->label(__("filament-theme-studio::theme-studio.fields.{$name}"))
                ->required()
                ->regex('/^#[0-9A-Fa-f]{6}$/'),
            $names,
        );
    }

    private static function radiusField(string $name): TextInput
    {
        return TextInput::make("settings.shape.{$name}")
            ->label(__("filament-theme-studio::theme-studio.fields.{$name}"))
            ->numeric()->required()->minValue(0)->maxValue(40)->suffix('px');
    }
}
