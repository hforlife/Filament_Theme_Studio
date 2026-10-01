<?php

declare(strict_types=1);

namespace Hforlife\FilamentThemeStudio\Services;

use Hforlife\FilamentThemeStudio\Exceptions\InvalidCustomCss;
use Hforlife\FilamentThemeStudio\Models\Theme;
use Hforlife\FilamentThemeStudio\Support\CustomCssConfiguration;
use Illuminate\Contracts\Cache\Factory as CacheFactory;

final readonly class CustomCssPreview
{
    public function __construct(
        private CacheFactory $cache,
        private CustomCssValidator $validator,
    ) {}

    public function create(Theme $theme, int | string $userId, string $css): string
    {
        if (! CustomCssConfiguration::enabled()) {
            throw new InvalidCustomCss('Custom CSS is disabled by configuration.');
        }

        $result = $this->validator->validate($css);
        if (! $result->valid) {
            throw InvalidCustomCss::fromResult($result);
        }

        $token = bin2hex(random_bytes(32));
        $this->cache->store()->put($this->key($token), [
            'user_id' => (string) $userId,
            'theme_id' => (int) $theme->getKey(),
            'panel_id' => $theme->panel_id,
            'css' => $result->css,
        ], 600);
        $indexKey = $this->indexKey($theme);
        $keys = $this->cache->store()->get($indexKey, []);
        $keys = is_array($keys) ? array_values(array_filter($keys, is_string(...))) : [];
        $keys[] = $this->key($token);
        $this->cache->store()->put($indexKey, array_values(array_unique($keys)), 600);

        return $token;
    }

    public function consume(string $token, Theme $theme, int | string $userId): ?string
    {
        if (preg_match('/\A[a-f0-9]{64}\z/', $token) !== 1) {
            return null;
        }

        $payload = $this->cache->store()->get($this->key($token));
        if (! is_array($payload)
            || ($payload['user_id'] ?? null) !== (string) $userId
            || ($payload['theme_id'] ?? null) !== (int) $theme->getKey()
            || ($payload['panel_id'] ?? null) !== $theme->panel_id
            || ! is_string($payload['css'] ?? null)
        ) {
            return null;
        }

        return $payload['css'];
    }

    public function forgetTheme(Theme $theme, int | string $userId, string $token): void
    {
        if ($this->consume($token, $theme, $userId) !== null) {
            $this->cache->store()->forget($this->key($token));
        }
    }

    public function forgetAllForTheme(Theme $theme): void
    {
        $indexKey = $this->indexKey($theme);
        $keys = $this->cache->store()->get($indexKey, []);
        if (is_array($keys)) {
            foreach ($keys as $key) {
                if (is_string($key)) {
                    $this->cache->store()->forget($key);
                }
            }
        }

        $this->cache->store()->forget($indexKey);
    }

    private function key(string $token): string
    {
        return 'filament-theme-studio:custom-css-preview:' . hash('sha256', $token);
    }

    private function indexKey(Theme $theme): string
    {
        return 'filament-theme-studio:custom-css-preview-index:' . $theme->panel_id . ':' . $theme->getKey();
    }
}
