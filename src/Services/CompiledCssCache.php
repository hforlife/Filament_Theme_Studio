<?php

declare(strict_types=1);

namespace Hforlife\FilamentThemeStudio\Services;

use Hforlife\FilamentThemeStudio\Models\Theme;
use Hforlife\FilamentThemeStudio\Support\CompiledThemeCss;
use Hforlife\FilamentThemeStudio\Support\CssConfiguration;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

final readonly class CompiledCssCache
{
    public function __construct(
        private CacheFactory $cache,
        private CssCompiler $compiler,
    ) {}

    public function get(Theme $theme): CompiledThemeCss
    {
        if (! $this->enabled()) {
            return $this->compiler->compile($theme);
        }

        $key = $this->key($theme);
        $repository = $this->repository();
        $ttl = (int) config('filament-theme-studio.cache.ttl', 3600);
        $cached = $repository->get($key);

        if (is_array($cached)) {
            try {
                $compiled = CompiledThemeCss::fromArray($cached);

                if (! $this->belongsTo($compiled, $theme)) {
                    throw new \UnexpectedValueException('Compiled CSS cache metadata does not match the theme.');
                }

                $this->rememberKey($theme->panel_id, $key, $ttl);

                return $compiled;
            } catch (\Throwable) {
                $repository->forget($key);
            }
        } elseif ($cached !== null) {
            $repository->forget($key);
        }

        $compiled = $this->compiler->compile($theme);

        if ($ttl === 0) {
            $repository->forever($key, $compiled->toArray());
        } else {
            $repository->put($key, $compiled->toArray(), $ttl);
        }

        $this->rememberKey($theme->panel_id, $key, $ttl);

        return $compiled;
    }

    private function belongsTo(CompiledThemeCss $compiled, Theme $theme): bool
    {
        return $compiled->themeId === (int) $theme->getKey()
            && $compiled->panelId === $theme->panel_id
            && $compiled->compilerVersion === CssConfiguration::compilerVersion();
    }

    public function forgetPanel(string $panelId): void
    {
        if (! $this->enabled()) {
            return;
        }

        $repository = $this->repository();
        $indexKey = $this->indexKey($panelId);
        $keys = $repository->get($indexKey, []);

        if (is_array($keys)) {
            foreach ($keys as $key) {
                if (is_string($key)) {
                    $repository->forget($key);
                }
            }
        }

        $repository->forget($indexKey);
    }

    public function key(Theme $theme): string
    {
        $prefix = rtrim((string) config('filament-theme-studio.cache.prefix', 'filament-theme-studio'), ':');
        $hash = $this->compiler->settingsFingerprint($theme);

        return sprintf(
            '%s:compiled-css:v%s:panel:%s:theme:%s:%s',
            $prefix,
            CssConfiguration::compilerVersion(),
            $theme->panel_id,
            $theme->getKey(),
            $hash,
        );
    }

    private function rememberKey(string $panelId, string $key, int $ttl): void
    {
        $repository = $this->repository();
        $indexKey = $this->indexKey($panelId);
        $keys = $repository->get($indexKey, []);
        $keys = is_array($keys) ? array_values(array_filter($keys, is_string(...))) : [];

        if (! in_array($key, $keys, true)) {
            $keys[] = $key;
        }

        if ($ttl === 0) {
            $repository->forever($indexKey, $keys);

            return;
        }

        $repository->put($indexKey, $keys, $ttl);
    }

    private function indexKey(string $panelId): string
    {
        $prefix = rtrim((string) config('filament-theme-studio.cache.prefix', 'filament-theme-studio'), ':');

        return "{$prefix}:compiled-css:index:panel:{$panelId}";
    }

    private function enabled(): bool
    {
        return (bool) config('filament-theme-studio.cache.enabled', true);
    }

    private function repository(): CacheRepository
    {
        $store = config('filament-theme-studio.cache.store');

        return $store === null ? $this->cache->store() : $this->cache->store((string) $store);
    }
}
