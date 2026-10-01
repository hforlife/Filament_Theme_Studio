<?php

declare(strict_types=1);

namespace Hforlife\FilamentThemeStudio\Support;

final class CustomCssConfiguration
{
    public const VALIDATOR_VERSION = '1';

    public static function enabled(): bool
    {
        return config('filament-theme-studio.custom_css.enabled') === true;
    }

    public static function maxBytes(): int
    {
        $value = config('filament-theme-studio.custom_css.max_bytes', 50_000);

        return is_int($value) && $value >= 1_000 && $value <= 200_000 ? $value : 50_000;
    }

    public static function mode(): string
    {
        return config('filament-theme-studio.custom_css.mode') === 'strict' ? 'strict' : 'strict';
    }

    public static function allowOnAuthPages(): bool
    {
        return config('filament-theme-studio.custom_css.allow_on_auth_pages') === true;
    }

    public static function allowExternalUrls(): bool
    {
        return config('filament-theme-studio.custom_css.allow_external_urls') === true;
    }

    public static function allowedMediaQueries(): bool
    {
        return config('filament-theme-studio.custom_css.allowed_media_queries', true) === true;
    }

    public static function fingerprint(): string
    {
        return hash('sha256', implode('|', [
            self::VALIDATOR_VERSION,
            self::mode(),
            self::maxBytes(),
            self::allowOnAuthPages() ? '1' : '0',
            self::allowExternalUrls() ? '1' : '0',
            self::allowedMediaQueries() ? '1' : '0',
        ]));
    }
}
