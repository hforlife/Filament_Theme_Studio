<?php

declare(strict_types=1);

namespace Hforlife\FilamentThemeStudio\Services;

use InvalidArgumentException;

final class ColorPaletteGenerator
{
    /** @var array<int, float> */
    private const LIGHT_MIX = [50 => 0.95, 100 => 0.90, 200 => 0.75, 300 => 0.55, 400 => 0.30];

    /** @var array<int, float> */
    private const DARK_MIX = [600 => 0.12, 700 => 0.28, 800 => 0.45, 900 => 0.62, 950 => 0.78];

    /** @return array<int, string> */
    public function generate(string $hex): array
    {
        if (preg_match('/^#[0-9A-Fa-f]{6}$/', $hex) !== 1) {
            throw new InvalidArgumentException('A six-digit hexadecimal color is required.');
        }

        $base = [
            hexdec(substr($hex, 1, 2)),
            hexdec(substr($hex, 3, 2)),
            hexdec(substr($hex, 5, 2)),
        ];
        $palette = [];

        foreach (self::LIGHT_MIX as $shade => $amount) {
            $palette[$shade] = $this->mix($base, [255, 255, 255], $amount);
        }

        $palette[500] = $this->serialize($base);

        foreach (self::DARK_MIX as $shade => $amount) {
            $palette[$shade] = $this->mix($base, [0, 0, 0], $amount);
        }

        return $palette;
    }

    /** @param array{int, int, int} $from @param array{int, int, int} $to */
    private function mix(array $from, array $to, float $amount): string
    {
        return $this->serialize([
            (int) round($from[0] + (($to[0] - $from[0]) * $amount)),
            (int) round($from[1] + (($to[1] - $from[1]) * $amount)),
            (int) round($from[2] + (($to[2] - $from[2]) * $amount)),
        ]);
    }

    /** @param array{int, int, int} $rgb */
    private function serialize(array $rgb): string
    {
        return sprintf('rgb(%d %d %d)', ...array_map(fn (int $value): int => min(255, max(0, $value)), $rgb));
    }
}
