<?php

declare(strict_types=1);

namespace Hforlife\FilamentThemeStudio\Resources\ThemeResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Hforlife\FilamentThemeStudio\Resources\ThemeResource;

class ListThemes extends ListRecords
{
    protected static string $resource = ThemeResource::class;

    /** @return array<CreateAction> */
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
