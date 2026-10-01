<?php

declare(strict_types=1);

namespace Hforlife\FilamentThemeStudio\Database\Factories;

use Hforlife\FilamentThemeStudio\Models\Theme;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Theme> */
class ThemeFactory extends Factory
{
    protected $model = Theme::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'panel_id' => 'admin',
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'settings' => [],
            'custom_css' => null,
            'custom_css_enabled' => false,
            'is_active' => false,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function forPanel(string $panelId): static
    {
        return $this->state(fn (): array => ['panel_id' => $panelId]);
    }

    public function active(): static
    {
        return $this->state(fn (): array => ['is_active' => true]);
    }
}
