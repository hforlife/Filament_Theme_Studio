<?php

declare(strict_types=1);

namespace Hforlife\FilamentThemeStudio\Support;

final readonly class CustomCssIssue
{
    public function __construct(
        public string $message,
        public ?int $line = null,
        public ?int $column = null,
        public ?string $rule = null,
    ) {}

    /** @return array{message: string, line: int|null, column: int|null, rule: string|null} */
    public function toArray(): array
    {
        return [
            'message' => $this->message,
            'line' => $this->line,
            'column' => $this->column,
            'rule' => $this->rule,
        ];
    }
}
