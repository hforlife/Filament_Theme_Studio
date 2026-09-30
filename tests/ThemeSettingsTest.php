<?php

use Hforlife\FilamentThemeStudio\Support\ThemeSettings;
use Illuminate\Validation\ValidationException;

it('provides a complete canonical theme settings document', function () {
    expect(ThemeSettings::defaults())
        ->toHaveKeys(['colors', 'dark_colors', 'typography', 'shape', 'layout'])
        ->and(ThemeSettings::defaults()['colors'])->toHaveKeys([
            'primary', 'secondary', 'success', 'warning', 'danger', 'background', 'surface', 'text',
            'sidebar_background', 'sidebar_text',
        ])
        ->and(ThemeSettings::defaults()['dark_colors'])->toHaveKeys([
            'background', 'surface', 'text', 'sidebar_background', 'sidebar_text',
        ]);
});

it('normalizes numeric form values and preserves settings unknown to the editor', function () {
    $settings = ThemeSettings::normalize([
        'typography' => ['base_size' => '18'],
        'shape' => ['card_radius' => '20'],
        'layout' => ['sidebar_width' => '320'],
    ], [
        'future' => ['token' => 'kept'],
        'colors' => ['primary' => '#112233'],
    ]);

    expect($settings['typography']['base_size'])->toBe(18)
        ->and($settings['shape']['card_radius'])->toBe(20)
        ->and($settings['layout']['sidebar_width'])->toBe(320)
        ->and($settings['colors']['primary'])->toBe('#112233')
        ->and($settings['future']['token'])->toBe('kept');
});

it('rejects invalid canonical values', function (array $changes) {
    ThemeSettings::normalize($changes);
})->with([
    'color format' => [['colors' => ['primary' => 'blue']]],
    'font allow list' => [['typography' => ['font_family' => 'Remote Font']]],
    'font size range' => [['typography' => ['base_size' => 30]]],
    'radius range' => [['shape' => ['button_radius' => 41]]],
    'sidebar width range' => [['layout' => ['sidebar_width' => 100]]],
    'content width allow list' => [['layout' => ['content_max_width' => 'giant']]],
    'density allow list' => [['layout' => ['density' => 'dense']]],
])->throws(ValidationException::class);
