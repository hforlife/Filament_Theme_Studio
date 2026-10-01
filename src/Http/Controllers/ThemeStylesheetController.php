<?php

declare(strict_types=1);

namespace Hforlife\FilamentThemeStudio\Http\Controllers;

use Filament\Facades\Filament;
use Hforlife\FilamentThemeStudio\Models\Theme;
use Hforlife\FilamentThemeStudio\Services\CompiledCssCache;
use Hforlife\FilamentThemeStudio\Services\ThemeManager;
use Hforlife\FilamentThemeStudio\Support\CssConfiguration;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class ThemeStylesheetController
{
    public function __invoke(
        Request $request,
        string $panelId,
        ThemeManager $themes,
        CompiledCssCache $cache,
    ): Response {
        abort_unless(CssConfiguration::enabled(), 404);
        abort_unless(preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/', $panelId) === 1, 404);

        $panel = Filament::getPanels()[$panelId] ?? null;
        abort_unless($panel?->hasPlugin('filament-theme-studio') === true, 404);

        $theme = $themes->getActiveTheme($panelId);

        if (! $theme instanceof Theme) {
            return $this->response('', hash('sha256', ''), $request);
        }

        $compiled = $cache->get($theme);

        return $this->response($compiled->content, $compiled->fingerprint, $request);
    }

    private function response(string $content, string $fingerprint, Request $request): Response
    {
        $etag = '"' . $fingerprint . '"';
        $headers = [
            'Content-Type' => 'text/css; charset=UTF-8',
            'Cache-Control' => CssConfiguration::cacheControl(),
            'ETag' => $etag,
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($request->headers->get('If-None-Match') === $etag) {
            return response('', 304, $headers);
        }

        return response($content, 200, $headers);
    }
}
