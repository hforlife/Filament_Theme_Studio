<?php

declare(strict_types=1);

namespace Hforlife\FilamentThemeStudio\Http\Controllers;

use Filament\Facades\Filament;
use Hforlife\FilamentThemeStudio\FilamentThemeStudioPlugin;
use Hforlife\FilamentThemeStudio\Models\Theme;
use Hforlife\FilamentThemeStudio\Services\CustomCssPreview;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

final class CustomCssPreviewController
{
    public function __invoke(Request $request, string $panelId, Theme $theme, string $token, CustomCssPreview $previews): Response
    {
        abort_unless($theme->panel_id === $panelId, 404);
        $panel = Filament::getPanels()[$panelId] ?? null;
        $plugin = $panel?->getPlugin('filament-theme-studio');
        abort_unless($plugin instanceof FilamentThemeStudioPlugin && $plugin->isCustomCssAuthorized(), 403);

        $userId = Auth::id();
        abort_unless(is_int($userId) || is_string($userId), 403);
        $css = $previews->consume($token, $theme, $userId);
        abort_if($css === null, 404);

        return response(view('filament-theme-studio::custom-css-preview', ['css' => $css])->render())
            ->header('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'; frame-ancestors 'self'")
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Content-Type-Options', 'nosniff');
    }
}
