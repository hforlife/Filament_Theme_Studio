<?php

declare(strict_types=1);

namespace Hforlife\FilamentThemeStudio\Support;

final readonly class CustomCssValidationResult
{
    /**
     * @param  list<CustomCssIssue>  $errors
     * @param  list<CustomCssIssue>  $warnings
     * @param  list<string>  $rejectedRules
     */
    public function __construct(
        public bool $valid,
        public string $css,
        public array $errors = [],
        public array $warnings = [],
        public array $rejectedRules = [],
    ) {}

    public static function valid(string $css): self
    {
        return new self(true, $css);
    }

    /** @param list<CustomCssIssue> $errors @param list<string> $rejectedRules */
    public static function invalid(array $errors, array $rejectedRules = []): self
    {
        return new self(false, '', $errors, [], array_values(array_unique($rejectedRules)));
    }
}
