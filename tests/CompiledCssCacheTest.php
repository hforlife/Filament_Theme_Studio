<?php

use Hforlife\FilamentThemeStudio\Models\Theme;
use Hforlife\FilamentThemeStudio\Services\CompiledCssCache;
use Hforlife\FilamentThemeStudio\Services\CssCompiler;
use Hforlife\FilamentThemeStudio\Services\ThemeManager;
use Hforlife\FilamentThemeStudio\Support\CompiledThemeCss;
use Hforlife\FilamentThemeStudio\Support\CssConfiguration;
use Hforlife\FilamentThemeStudio\Support\ThemeSettings;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('isolates compiled entries by panel theme settings and compiler version', function () {
    $cache = app(CompiledCssCache::class);
    $admin = Theme::factory()->forPanel('admin')->create(['settings' => ThemeSettings::defaults()]);
    $staff = Theme::factory()->forPanel('staff')->create(['settings' => ThemeSettings::defaults()]);

    expect($cache->key($admin))->toContain(':compiled-css:v1:panel:admin:theme:' . $admin->id . ':')
        ->and($cache->key($staff))->toContain(':compiled-css:v1:panel:staff:theme:' . $staff->id . ':')
        ->and($cache->key($admin))->not->toBe($cache->key($staff));
});

it('supports disabled caching and entries without expiration', function () {
    $cache = app(CompiledCssCache::class);
    $theme = Theme::factory()->create(['settings' => ThemeSettings::defaults()]);
    $key = $cache->key($theme);

    config()->set('filament-theme-studio.cache.enabled', false);
    $cache->get($theme);
    expect(Cache::has($key))->toBeFalse();

    config()->set('filament-theme-studio.cache.enabled', true);
    config()->set('filament-theme-studio.cache.ttl', 0);
    $cache->get($theme);
    expect(Cache::get($key))->toBeArray();
});

it('replaces legacy cached objects with scalar arrays', function () {
    $cache = app(CompiledCssCache::class);
    $theme = Theme::factory()->create(['settings' => ThemeSettings::defaults()]);
    $key = $cache->key($theme);
    $legacy = app(CssCompiler::class)->compile($theme);

    Cache::put($key, $legacy);

    $compiled = $cache->get($theme);

    expect($compiled)->toBeInstanceOf(CompiledThemeCss::class)
        ->and(Cache::get($key))->toBe($compiled->toArray());
});

it('replaces incomplete PHP objects and unexpected cache values', function ($value) {
    $cache = app(CompiledCssCache::class);
    $theme = Theme::factory()->create(['settings' => ThemeSettings::defaults()]);
    $key = $cache->key($theme);

    Cache::put($key, $value);

    $compiled = $cache->get($theme);

    expect($compiled)->toBeInstanceOf(CompiledThemeCss::class)
        ->and(Cache::get($key))->toBe($compiled->toArray());
})->with([
    'incomplete PHP class' => fn () => unserialize('O:19:"LegacyCompiledTheme":0:{}'),
    'unexpected scalar' => 'invalid-cache-value',
]);

it('rejects malformed or mismatched array payloads', function (Closure $payload) {
    $cache = app(CompiledCssCache::class);
    $theme = Theme::factory()->forPanel('admin')->create(['settings' => ThemeSettings::defaults()]);
    $key = $cache->key($theme);
    $valid = app(CssCompiler::class)->compile($theme)->toArray();

    Cache::put($key, $payload($valid));

    $compiled = $cache->get($theme);

    expect($compiled->themeId)->toBe($theme->id)
        ->and($compiled->panelId)->toBe('admin')
        ->and($compiled->compilerVersion)->toBe(CssConfiguration::compilerVersion())
        ->and(Cache::get($key))->toBe($compiled->toArray());
})->with([
    'incomplete array' => [fn (array $payload): array => array_diff_key($payload, ['content' => true])],
    'wrong theme id' => [fn (array $payload): array => [...$payload, 'theme_id' => $payload['theme_id'] + 1]],
    'wrong panel id' => [fn (array $payload): array => [...$payload, 'panel_id' => 'staff']],
    'wrong compiler version' => [fn (array $payload): array => [...$payload, 'compiler_version' => 'legacy']],
    'wrong fingerprint' => [fn (array $payload): array => [...$payload, 'fingerprint' => str_repeat('0', 64)]],
    'wrong field type' => [fn (array $payload): array => [...$payload, 'theme_id' => (string) $payload['theme_id']]],
]);

it('reconstructs compiled css from a valid cached array on subsequent reads', function () {
    $cache = app(CompiledCssCache::class);
    $theme = Theme::factory()->create(['settings' => ThemeSettings::defaults()]);
    $key = $cache->key($theme);

    $first = $cache->get($theme);
    $payload = $first->toArray();
    $payload['content'] = '/* cached scalar payload */';
    $payload['fingerprint'] = hash('sha256', $payload['content']);
    Cache::put($key, $payload);

    $second = $cache->get($theme);

    expect($second)->not->toBe($first)
        ->and($second->content)->toBe('/* cached scalar payload */')
        ->and($second->toArray())->toBe($payload);
});

it('stores and reconstructs scalar payloads with the database cache store', function () {
    Schema::create('cache', function (Blueprint $table): void {
        $table->string('key')->primary();
        $table->mediumText('value');
        $table->integer('expiration');
    });

    config()->set('cache.stores.database', [
        'driver' => 'database',
        'connection' => 'testing',
        'table' => 'cache',
        'lock_connection' => 'testing',
        'lock_table' => 'cache_locks',
    ]);
    config()->set('filament-theme-studio.cache.store', 'database');

    $cache = app(CompiledCssCache::class);
    $theme = Theme::factory()->create(['settings' => ThemeSettings::defaults()]);
    $key = $cache->key($theme);
    $first = $cache->get($theme);
    $repository = Cache::store('database');

    expect($repository->get($key))->toBe($first->toArray())
        ->and((string) DB::table('cache')->value('value'))
        ->not->toContain(CompiledThemeCss::class);

    $payload = $first->toArray();
    $payload['content'] = '/* database cache hit */';
    $payload['fingerprint'] = hash('sha256', $payload['content']);
    $repository->put($key, $payload, 3600);

    expect($cache->get($theme)->content)->toBe('/* database cache hit */');
});

it('invalidates compiled css after successful mutations but not after a rollback', function () {
    $cache = app(CompiledCssCache::class);
    $manager = app(ThemeManager::class);
    $theme = Theme::factory()->forPanel('admin')->active()->create(['settings' => ThemeSettings::defaults()]);
    $key = $cache->key($theme);
    $cache->get($theme);

    $manager->updateTheme($theme, ['settings' => ThemeSettings::normalize(['colors' => ['primary' => '#abcdef']])]);
    expect(Cache::has($key))->toBeFalse();

    $next = Theme::factory()->forPanel('admin')->create(['settings' => ThemeSettings::defaults()]);
    $failedKey = $cache->key($next);
    $cache->get($next);
    Theme::updating(function (Theme $candidate) use ($next): void {
        if ($candidate->is($next)) {
            throw new LogicException('rollback');
        }
    });

    expect(fn () => $manager->activateTheme($next))->toThrow(LogicException::class)
        ->and(Cache::has($failedKey))->toBeTrue();
});

afterEach(function () {
    Theme::flushEventListeners();
});
