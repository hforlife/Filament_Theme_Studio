<?php

use Hforlife\FilamentThemeStudio\Models\Theme;
use Hforlife\FilamentThemeStudio\Services\CssCompiler;
use Hforlife\FilamentThemeStudio\Support\ThemeSettings;

it('compiles every structured category deterministically', function () {
    $theme = Theme::factory()->create(['settings' => ThemeSettings::normalize([
        'colors' => ['primary' => '#123456', 'background' => '#fefefe', 'surface' => '#eeeeee'],
        'dark_colors' => ['background' => '#010203'],
        'typography' => ['font_family' => 'Georgia', 'base_size' => 18],
        'shape' => ['border_radius' => 4, 'button_radius' => 6, 'input_radius' => 7, 'card_radius' => 9],
        'layout' => ['sidebar_width' => 320, 'content_max_width' => 'screen-xl', 'density' => 'compact'],
    ])]);
    $compiler = app(CssCompiler::class);
    $first = $compiler->compile($theme);
    $second = $compiler->compile($theme);

    expect($first->content)->toBe($second->content)
        ->and($first->fingerprint)->toBe($second->fingerprint)
        ->and($first->content)->toContain(
            '--fts-primary: #123456;',
            '--fts-background: #fefefe;',
            ':root.dark {',
            '--fts-background: #010203;',
            '--fts-font-family: Georgia, serif;',
            '--fts-base-font-size: 18px;',
            '--fts-button-radius: 6px;',
            '--fts-sidebar-width: 320px;',
            '--fts-content-max-width: 1280px;',
            '--fts-density-space: 0.75rem;',
            '--primary-950: rgb(',
        );
});

it('never includes custom css or business metadata', function () {
    $theme = Theme::factory()->create([
        'name' => 'Sensitive theme name',
        'custom_css' => '@import url(https://evil.test/x.css); .owned { display:none }',
        'settings' => ThemeSettings::defaults(),
        'updated_by' => 'private-user',
    ]);
    $css = app(CssCompiler::class)->compile($theme)->content;

    expect($css)->not->toContain('@import', 'evil.test', '.owned', 'Sensitive theme name', 'private-user');
});

it('falls back atomically to safe defaults for corrupted stored settings', function (array $settings, string $payload) {
    $theme = Theme::factory()->create(['settings' => $settings]);
    $compiled = app(CssCompiler::class)->compile($theme);

    expect($compiled->content)->not->toContain($payload)
        ->and($compiled->content)->toContain('--fts-primary: #3b82f6;');
})->with([
    'semicolon' => [['colors' => ['primary' => '#000000;display:none']], 'display:none'],
    'braces' => [['typography' => ['font_family' => 'Arial}body{']], 'body{'],
    'url' => [['typography' => ['font_family' => 'url(evil)']], 'url(evil)'],
    'import' => [['layout' => ['density' => '@import']], '@import'],
    'html' => [['layout' => ['content_max_width' => '<style>']], '<style>'],
]);

it('changes its fingerprint only when effective structured settings change', function () {
    $theme = Theme::factory()->create(['settings' => ThemeSettings::defaults(), 'custom_css' => '.one {}']);
    $compiler = app(CssCompiler::class);
    $initial = $compiler->compile($theme)->fingerprint;

    $theme->custom_css = '.two {}';
    expect($compiler->compile($theme)->fingerprint)->toBe($initial);

    $settings = $theme->settings;
    $settings['colors']['primary'] = '#abcdef';
    $theme->settings = $settings;

    expect($compiler->compile($theme)->fingerprint)->not->toBe($initial);
});
