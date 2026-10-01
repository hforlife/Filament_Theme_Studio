<?php

declare(strict_types=1);

namespace Hforlife\FilamentThemeStudio\Support;

use UnexpectedValueException;

final readonly class CompiledThemeCss
{
    public function __construct(
        public string $content,
        public string $fingerprint,
        public int $themeId,
        public string $panelId,
        public string $compilerVersion,
    ) {}

    public function etag(): string
    {
        return '"' . $this->fingerprint . '"';
    }

    /**
     * @return array{
     *     content: string,
     *     fingerprint: string,
     *     theme_id: int,
     *     panel_id: string,
     *     compiler_version: string
     * }
     */
    public function toArray(): array
    {
        return [
            'content' => $this->content,
            'fingerprint' => $this->fingerprint,
            'theme_id' => $this->themeId,
            'panel_id' => $this->panelId,
            'compiler_version' => $this->compilerVersion,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        foreach (['content', 'fingerprint', 'theme_id', 'panel_id', 'compiler_version'] as $key) {
            if (! array_key_exists($key, $data)) {
                throw new UnexpectedValueException("Missing compiled CSS cache field [{$key}].");
            }
        }

        if (! is_string($data['content'])
            || ! is_string($data['fingerprint'])
            || ! is_int($data['theme_id'])
            || ! is_string($data['panel_id'])
            || ! is_string($data['compiler_version'])
        ) {
            throw new UnexpectedValueException('Compiled CSS cache fields have invalid types.');
        }

        if ($data['theme_id'] < 1
            || $data['panel_id'] === ''
            || $data['compiler_version'] === ''
            || preg_match('/\A[a-f0-9]{64}\z/', $data['fingerprint']) !== 1
            || ! hash_equals(hash('sha256', $data['content']), $data['fingerprint'])
        ) {
            throw new UnexpectedValueException('Compiled CSS cache fields have invalid values.');
        }

        return new self(
            content: $data['content'],
            fingerprint: $data['fingerprint'],
            themeId: $data['theme_id'],
            panelId: $data['panel_id'],
            compilerVersion: $data['compiler_version'],
        );
    }
}
