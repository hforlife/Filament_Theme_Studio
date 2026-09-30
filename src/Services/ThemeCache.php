<?php

declare(strict_types=1);

namespace Hforlife\FilamentThemeStudio\Services;

use Hforlife\FilamentThemeStudio\Models\Theme;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

class ThemeCache
{
    public function __construct(private readonly CacheFactory $cache) {}

    public function getActiveTheme(string $panelId): ?Theme
    {
        if (! $this->enabled()) {
            return $this->queryActiveTheme($panelId);
        }

        $repository = $this->repository();
        $key = $this->key($panelId);
        $resolveId = function () use ($panelId): ?int {
            $theme = $this->queryActiveTheme($panelId);

            if (! $theme instanceof Theme) {
                return null;
            }

            return (int) $theme->getKey();
        };
        $ttl = (int) config('filament-theme-studio.cache.ttl', 3600);

        $themeId = $ttl === 0
            ? $repository->rememberForever($key, $resolveId)
            : $repository->remember($key, $ttl, $resolveId);

        return is_int($themeId) ? Theme::query()->find($themeId) : null;
    }

    public function forget(string $panelId): void
    {
        if ($this->enabled()) {
            $this->repository()->forget($this->key($panelId));
        }
    }

    public function key(string $panelId): string
    {
        $prefix = rtrim((string) config('filament-theme-studio.cache.prefix', 'filament-theme-studio'), ':');

        return "{$prefix}:panel:{$panelId}:active-theme";
    }

    private function enabled(): bool
    {
        return (bool) config('filament-theme-studio.cache.enabled', true);
    }

    private function repository(): CacheRepository
    {
        $store = config('filament-theme-studio.cache.store');

        return $store === null
            ? $this->cache->store()
            : $this->cache->store((string) $store);
    }

    private function queryActiveTheme(string $panelId): ?Theme
    {
        return Theme::query()
            ->where('panel_id', $panelId)
            ->where('is_active', true)
            ->first();
    }
}
