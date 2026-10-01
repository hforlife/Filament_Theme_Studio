<?php

declare(strict_types=1);

namespace Hforlife\FilamentThemeStudio\Support;

final class CssConfiguration
{
    public const DEFAULT_ROUTE_PREFIX = 'filament-theme-studio/styles';

    public static function enabled(): bool
    {
        return (bool) config('filament-theme-studio.enabled', true)
            && (bool) config('filament-theme-studio.css.enabled', true);
    }

    public static function routePrefix(): string
    {
        $prefix = trim((string) config('filament-theme-studio.css.route_prefix', self::DEFAULT_ROUTE_PREFIX), '/');

        return preg_match('/^[A-Za-z0-9_-]+(?:\/[A-Za-z0-9_-]+)*$/', $prefix) === 1
            ? $prefix
            : self::DEFAULT_ROUTE_PREFIX;
    }

    public static function cacheControl(): string
    {
        $value = (string) config('filament-theme-studio.css.cache_control', 'public, max-age=3600');

        return preg_match('/^[A-Za-z0-9 ,=_-]+$/', $value) === 1
            ? $value
            : 'public, max-age=3600';
    }

    public static function compilerVersion(): string
    {
        $version = (string) config('filament-theme-studio.css.compiler_version', '1');

        return preg_match('/^[A-Za-z0-9._-]+$/', $version) === 1 ? $version : '1';
    }
}
