<?php

declare(strict_types=1);

namespace Hforlife\FilamentThemeStudio\Console\Commands;

use Hforlife\FilamentThemeStudio\Models\Theme;
use Hforlife\FilamentThemeStudio\Services\ThemeManager;
use Illuminate\Console\Command;

final class DisableCustomCssCommand extends Command
{
    protected $signature = 'filament-theme-studio:disable-custom-css
        {--panel= : Disable custom CSS for themes in this panel}
        {--theme= : Disable custom CSS for one theme ID}
        {--all : Disable custom CSS for every theme}
        {--force : Skip production confirmation}';

    protected $description = 'Disable custom CSS without disabling structured themes';

    public function handle(ThemeManager $themes): int
    {
        $targets = array_filter([
            $this->option('panel'),
            $this->option('theme'),
            $this->option('all') === true ? 'all' : null,
        ], static fn (mixed $value): bool => ! in_array($value, [null, false, ''], true));

        if (count($targets) !== 1) {
            $this->components->error(__('filament-theme-studio::theme-studio.custom_css.command.target_required'));

            return self::INVALID;
        }

        if (app()->environment('production') && ! $this->option('force')
            && ! $this->confirm(__('filament-theme-studio::theme-studio.custom_css.command.confirm'))) {
            return self::FAILURE;
        }

        $query = Theme::query()->where('custom_css_enabled', true);
        if (is_string($this->option('panel')) && $this->option('panel') !== '') {
            $query->where('panel_id', $this->option('panel'));
        } elseif ((is_string($this->option('theme')) || is_int($this->option('theme'))) && $this->option('theme') !== '') {
            $query->whereKey($this->option('theme'));
        }

        $count = 0;
        $query->eachById(function (Theme $theme) use ($themes, &$count): void {
            $themes->setCustomCssEnabled($theme, false);
            $count++;
        });

        $this->components->info(__('filament-theme-studio::theme-studio.custom_css.command.disabled', ['count' => $count]));

        return self::SUCCESS;
    }
}
