<?php

use Hforlife\FilamentThemeStudio\Services\ColorPaletteGenerator;

it('generates a deterministic bounded Filament palette', function (string $color) {
    $generator = app(ColorPaletteGenerator::class);
    $first = $generator->generate($color);

    expect($first)->toBe($generator->generate($color))
        ->and(array_keys($first))->toBe([50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950])
        ->and($first)->each->toMatch('/^rgb\((?:\d{1,3}) (?:\d{1,3}) (?:\d{1,3})\)$/');
})->with(['blue' => '#3b82f6', 'black' => '#000000', 'white' => '#ffffff']);

it('rejects malformed palette input', function (string $color) {
    app(ColorPaletteGenerator::class)->generate($color);
})->with(['short hex' => '#fff', 'css function' => 'url(evil)', 'declaration' => '#000000;display:none'])
    ->throws(InvalidArgumentException::class);
