<?php

declare(strict_types=1);

namespace Hforlife\FilamentThemeStudio\Services;

use Hforlife\FilamentThemeStudio\Support\CustomCssConfiguration;
use Hforlife\FilamentThemeStudio\Support\CustomCssIssue;
use Hforlife\FilamentThemeStudio\Support\CustomCssValidationResult;
use Sabberworm\CSS\CSSList\AtRuleBlockList;
use Sabberworm\CSS\CSSList\CSSList;
use Sabberworm\CSS\OutputFormat;
use Sabberworm\CSS\Parser;
use Sabberworm\CSS\Position\Positionable;
use Sabberworm\CSS\Property\Declaration;
use Sabberworm\CSS\RuleSet\DeclarationBlock;
use Sabberworm\CSS\Settings;
use Sabberworm\CSS\Value\Value;
use Throwable;

final class CustomCssValidator
{
    /** @var list<string> */
    private const ALLOWED_PROPERTIES = [
        'color', 'background', 'background-color', 'border', 'border-color', 'border-style', 'border-width',
        'border-radius', 'box-shadow', 'opacity', 'font-family', 'font-size', 'font-style', 'font-weight',
        'letter-spacing', 'line-height', 'text-align', 'text-decoration', 'text-transform', 'padding',
        'padding-top', 'padding-right', 'padding-bottom', 'padding-left', 'margin', 'margin-top', 'margin-right',
        'margin-bottom', 'margin-left', 'gap', 'row-gap', 'column-gap', 'width', 'min-width', 'max-width',
        'height', 'min-height', 'max-height', 'overflow', 'overflow-x', 'overflow-y',
    ];

    /** @var list<string> */
    private const SAFE_FUNCTIONS = ['rgb', 'rgba', 'hsl', 'hsla', 'calc', 'min', 'max', 'clamp', 'var'];

    public function validate(string $css): CustomCssValidationResult
    {
        if (strlen($css) > CustomCssConfiguration::maxBytes()) {
            return $this->failure(__('filament-theme-studio::theme-studio.custom_css.errors.too_large', [
                'max' => CustomCssConfiguration::maxBytes(),
            ]));
        }

        if ($css === '' || trim($css) === '') {
            return CustomCssValidationResult::valid('');
        }

        if (! mb_check_encoding($css, 'UTF-8')) {
            return $this->failure(__('filament-theme-studio::theme-studio.custom_css.errors.invalid_encoding'));
        }

        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $css) === 1 || str_contains($css, '<') || str_contains($css, '>')) {
            return $this->failure(__('filament-theme-studio::theme-studio.custom_css.errors.html'));
        }

        $decoded = $this->decodeEscapes((string) preg_replace('~/\*.*?\*/~s', '', $css));
        if (preg_match('/@(import|namespace|font-face|document|keyframes)\b|\b(behavior|-moz-binding)\s*:|\b(expression|url)\s*\(|\b(?:java|vb)script\s*:/i', $decoded, $match) === 1) {
            return $this->failure(__('filament-theme-studio::theme-studio.custom_css.errors.forbidden_construct'), strtolower($match[0]));
        }

        try {
            $document = (new Parser($css, Settings::create()->beStrict()))->parse();
            $parsed = $this->walk($document, 0);

            return $parsed['errors'] === []
                ? CustomCssValidationResult::valid($parsed['css'])
                : CustomCssValidationResult::invalid($parsed['errors'], $parsed['rejected']);
        } catch (Throwable $exception) {
            $line = method_exists($exception, 'getLineNumber') ? $exception->getLineNumber() : null;

            return CustomCssValidationResult::invalid([
                new CustomCssIssue(__('filament-theme-studio::theme-studio.custom_css.errors.syntax'), is_int($line) ? $line : null),
            ]);
        }
    }

    /** @return array{css: string, errors: list<CustomCssIssue>, rejected: list<string>} */
    private function walk(CSSList $list, int $depth): array
    {
        if ($depth > 8) {
            return [
                'css' => '',
                'errors' => [new CustomCssIssue(__('filament-theme-studio::theme-studio.custom_css.errors.too_deep'))],
                'rejected' => [],
            ];
        }

        $output = [];
        $errors = [];
        $rejected = [];
        foreach ($list->getContents() as $item) {
            if ($item instanceof DeclarationBlock) {
                $parsed = $this->declarationBlock($item);
                array_push($errors, ...$parsed['errors']);
                array_push($rejected, ...$parsed['rejected']);
                if ($parsed['css'] !== '') {
                    $output[] = $parsed['css'];
                }

                continue;
            }

            if ($item instanceof AtRuleBlockList) {
                $name = strtolower($item->atRuleName());
                $arguments = trim($item->atRuleArgs());
                if (! $this->validAtRule($name, $arguments)) {
                    $errors[] = $this->issue($item, __('filament-theme-studio::theme-studio.custom_css.errors.at_rule', ['rule' => $name]), '@' . $name);
                    $rejected[] = '@' . $name;

                    continue;
                }

                $children = $this->walk($item, $depth + 1);
                array_push($errors, ...$children['errors']);
                array_push($rejected, ...$children['rejected']);
                if ($children['css'] !== '') {
                    $output[] = "@{$name} {$arguments} {\n{$children['css']}\n}";
                }

                continue;
            }

            $errors[] = $this->issue($item instanceof Positionable ? $item : null, __('filament-theme-studio::theme-studio.custom_css.errors.unknown_rule'));
        }

        return ['css' => implode("\n", $output), 'errors' => $errors, 'rejected' => $rejected];
    }

    /** @return array{css: string, errors: list<CustomCssIssue>, rejected: list<string>} */
    private function declarationBlock(DeclarationBlock $block): array
    {
        $selectors = [];
        $errors = [];
        $rejected = [];
        foreach ($block->getSelectors() as $selector) {
            $raw = trim($this->decodeEscapes($selector->getSelector()));
            if (! $this->validSelector($raw)) {
                $errors[] = $this->issue($block, __('filament-theme-studio::theme-studio.custom_css.errors.selector', ['selector' => $raw]), $raw);
                $rejected[] = $raw;

                continue;
            }
            $selectors[] = str_starts_with($raw, '.fi-body') ? $raw : '.fi-body ' . $raw;
        }

        $declarations = [];
        foreach ($block->getDeclarations() as $declaration) {
            $property = strtolower($this->decodeEscapes($declaration->getPropertyName()));
            if (! in_array($property, self::ALLOWED_PROPERTIES, true) && ! str_starts_with($property, '--fts-custom-')) {
                $errors[] = $this->issue($declaration, __('filament-theme-studio::theme-studio.custom_css.errors.property', ['property' => $property]), $property);
                $rejected[] = $property;

                continue;
            }

            $value = $this->renderValue($declaration);
            if (! $this->validValue($value)) {
                $errors[] = $this->issue($declaration, __('filament-theme-studio::theme-studio.custom_css.errors.value', ['property' => $property]), $property);
                $rejected[] = $property;

                continue;
            }

            $declarations[] = "  {$property}: {$value}" . ($declaration->getIsImportant() ? ' !important' : '') . ';';
        }

        if ($selectors === [] || $declarations === []) {
            if ($block->getDeclarations() === []) {
                $errors[] = $this->issue($block, __('filament-theme-studio::theme-studio.custom_css.errors.empty_rule'));
            }

            return ['css' => '', 'errors' => $errors, 'rejected' => $rejected];
        }

        return [
            'css' => implode(",\n", $selectors) . " {\n" . implode("\n", $declarations) . "\n}",
            'errors' => $errors,
            'rejected' => $rejected,
        ];
    }

    private function validSelector(string $selector): bool
    {
        $lower = strtolower($selector);

        return strlen($selector) <= 500
            && preg_match('/[\x00-\x1F<>]/', $selector) !== 1
            && preg_match('/[^\x20-\x7E]/', $selector) !== 1
            && preg_match('/(^|[\s,>+~])(html|body|script|style|iframe|object|embed)(?:[\s.#:[>+~]|$)/i', $selector) !== 1
            && preg_match('/(^|[\s,>+~])\*(?:[\s.#:[>+~]|$)/', $selector) !== 1
            && preg_match('/::|:has\s*\(|:host\b|:global\s*\(/i', $selector) !== 1
            && preg_match('/\[(?:type\s*=\s*["\']?hidden|name\s*[*^$|~]?=\s*["\']?(?:_token|csrf|password|secret|token))/i', $selector) !== 1
            && ! str_contains($lower, ':root')
            && ! preg_match('/(?:login|register|password|auth|mfa|verification)/i', $selector)
            && (str_contains($selector, '.fi-') || str_starts_with($selector, '[data-fi-'));
    }

    private function validValue(string $value): bool
    {
        $decoded = strtolower($this->decodeEscapes((string) preg_replace('~/\*.*?\*/~s', '', $value)));
        if (preg_match('/\b(url|expression)\s*\(|\b(?:java|vb)script\s*:|[<>\x00-\x1F]/i', $decoded) === 1) {
            return false;
        }

        preg_match_all('/([a-z-]+)\s*\(/i', $decoded, $matches);
        foreach ($matches[1] as $function) {
            if (! in_array(strtolower($function), self::SAFE_FUNCTIONS, true)) {
                return false;
            }
        }

        if (preg_match_all('/var\s*\(\s*(--[a-z0-9_-]+)/i', $decoded, $variables)) {
            foreach ($variables[1] as $variable) {
                if (! str_starts_with(strtolower($variable), '--fts-')) {
                    return false;
                }
            }
        }

        return strlen($value) <= 2_000;
    }

    private function validAtRule(string $name, string $arguments): bool
    {
        if (! in_array($name, ['media', 'supports', 'layer'], true) || strlen($arguments) > 500) {
            return false;
        }

        if ($name === 'media') {
            return CustomCssConfiguration::allowedMediaQueries()
                && preg_match('/\A[\s(),.:0-9a-z-]+\z/i', $arguments) === 1
                && preg_match('/\b(width|height|orientation|prefers-color-scheme|prefers-reduced-motion)\b/i', $arguments) === 1;
        }

        if ($name === 'layer') {
            return preg_match('/\A[a-z][a-z0-9_.-]*\z/i', $arguments) === 1;
        }

        return preg_match('/\A[\s(),.:#%0-9a-z-]+\z/i', $arguments) === 1
            && preg_match('/\b(url|expression)\s*\(/i', $arguments) !== 1;
    }

    private function renderValue(Declaration $declaration): string
    {
        $value = $declaration->getValue();

        return $value instanceof Value
            ? trim($value->render(OutputFormat::createCompact()))
            : trim((string) $value);
    }

    private function decodeEscapes(string $value): string
    {
        return (string) preg_replace_callback('/\\\\([0-9a-f]{1,6})\s?|\\\\(.)/iu', static function (array $match): string {
            if ($match[1] !== '') {
                $code = hexdec($match[1]);

                return $code <= 0x7F ? chr($code) : mb_chr($code, 'UTF-8');
            }

            return $match[2];
        }, $value);
    }

    private function issue(?Positionable $source, string $message, ?string $rule = null): CustomCssIssue
    {
        return new CustomCssIssue($message, $source?->getLineNumber(), $source?->getColumnNumber(), $rule);
    }

    private function failure(string $message, ?string $rule = null): CustomCssValidationResult
    {
        return CustomCssValidationResult::invalid([new CustomCssIssue($message, rule: $rule)], $rule === null ? [] : [$rule]);
    }
}
