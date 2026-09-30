<?php

declare(strict_types=1);

namespace Hforlife\FilamentThemeStudio\Database\Factories;

use Hforlife\FilamentThemeStudio\Models\Theme;
use Hforlife\FilamentThemeStudio\Models\ThemeVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ThemeVersion> */
class ThemeVersionFactory extends Factory
{
    protected $model = ThemeVersion::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'theme_id' => Theme::factory(),
            'version' => 1,
            'settings' => [],
            'custom_css' => null,
            'change_note' => null,
            'created_by' => null,
        ];
    }
}
