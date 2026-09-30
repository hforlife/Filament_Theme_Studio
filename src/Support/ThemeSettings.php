<?php

declare(strict_types=1);

namespace Hforlife\FilamentThemeStudio\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;

final class ThemeSettings
{
    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return [
            'colors' => [
                'primary' => '#3b82f6',
                'secondary' => '#64748b',
                'success' => '#22c55e',
                'warning' => '#f59e0b',
                'danger' => '#ef4444',
                'background' => '#ffffff',
                'surface' => '#ffffff',
                'text' => '#111827',
                'sidebar_background' => '#ffffff',
                'sidebar_text' => '#374151',
            ],
            'dark_colors' => [
                'background' => '#09090b',
                'surface' => '#18181b',
                'text' => '#fafafa',
                'sidebar_background' => '#18181b',
                'sidebar_text' => '#e4e4e7',
            ],
            'typography' => [
                'font_family' => 'Inter',
                'base_size' => 16,
            ],
            'shape' => [
                'border_radius' => 8,
                'button_radius' => 8,
                'input_radius' => 8,
                'card_radius' => 12,
            ],
            'layout' => [
                'sidebar_width' => 288,
                'content_max_width' => 'full',
                'density' => 'comfortable',
            ],
        ];
    }

    /** @return array<string, string|array<int, string>> */
    public static function rules(): array
    {
        $hex = ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'];

        return [
            'colors.primary' => $hex,
            'colors.secondary' => $hex,
            'colors.success' => $hex,
            'colors.warning' => $hex,
            'colors.danger' => $hex,
            'colors.background' => $hex,
            'colors.surface' => $hex,
            'colors.text' => $hex,
            'colors.sidebar_background' => $hex,
            'colors.sidebar_text' => $hex,
            'dark_colors.background' => $hex,
            'dark_colors.surface' => $hex,
            'dark_colors.text' => $hex,
            'dark_colors.sidebar_background' => $hex,
            'dark_colors.sidebar_text' => $hex,
            'typography.font_family' => 'required|in:Inter,system-ui,Arial,Helvetica,Georgia,sans-serif,serif,monospace',
            'typography.base_size' => 'required|integer|between:12,24',
            'shape.border_radius' => 'required|integer|between:0,40',
            'shape.button_radius' => 'required|integer|between:0,40',
            'shape.input_radius' => 'required|integer|between:0,40',
            'shape.card_radius' => 'required|integer|between:0,40',
            'layout.sidebar_width' => 'required|integer|between:200,420',
            'layout.content_max_width' => 'required|in:full,screen-xl,screen-2xl,screen-3xl,screen-4xl,screen-5xl,screen-6xl,screen-7xl',
            'layout.density' => 'required|in:compact,comfortable,spacious',
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>  $existing
     * @return array<string, mixed>
     */
    public static function normalize(array $settings, array $existing = []): array
    {
        $normalized = array_replace_recursive(self::defaults(), $existing, $settings);

        foreach (['typography.base_size', 'shape.border_radius', 'shape.button_radius', 'shape.input_radius', 'shape.card_radius', 'layout.sidebar_width'] as $path) {
            Arr::set($normalized, $path, (int) Arr::get($normalized, $path));
        }

        Validator::make($normalized, self::rules())->validate();

        return $normalized;
    }

    /** @return array<string> */
    public static function fontFamilies(): array
    {
        return ['Inter', 'system-ui', 'Arial', 'Helvetica', 'Georgia', 'sans-serif', 'serif', 'monospace'];
    }
}
