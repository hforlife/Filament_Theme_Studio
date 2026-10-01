<?php

use Hforlife\FilamentThemeStudio\Services\CustomCssValidator;

it('accepts normalizes and scopes safe visual CSS', function () {
    $result = app(CustomCssValidator::class)->validate(<<<'CSS'
.fi-btn:hover, .fi-input:focus {
    color: rgb(10, 20, 30);
    border-radius: clamp(4px, 1rem, 24px);
    padding: var(--fts-density-space);
}
@media (max-width: 640px) { .fi-section { margin: 1rem; } }
CSS);

    expect($result->valid)->toBeTrue()
        ->and($result->css)->toContain('.fi-body .fi-btn:hover')
        ->and($result->css)->toContain('@media (max-width: 640px)')
        ->and($result->errors)->toBe([]);
});

it('rejects dangerous and obfuscated CSS fail closed', function (string $css) {
    $result = app(CustomCssValidator::class)->validate($css);

    expect($result->valid)->toBeFalse()
        ->and($result->css)->toBe('')
        ->and($result->errors)->not->toBeEmpty();
})->with([
    'import' => '@import url("https://example.com/x.css");',
    'background URL' => '.fi-x { background: url("https://example.com/a.png"); }',
    'mixed-case URL' => '.fi-x { background: uRl(https://example.com/a); }',
    'comment-obfuscated URL' => '.fi-x { background: u/**/rl(https://example.com/a); }',
    'escaped URL' => '.fi-x { background: \\75rl(https://example.com/a); }',
    'behavior' => '.fi-x { behavior: url(test.htc); }',
    'moz binding' => '.fi-x { -moz-binding: url(test.xml); }',
    'expression' => '.fi-x { width: expression(alert(1)); }',
    'HTML breakout' => '.fi-x { color: red; } </style><script>alert(1)</script>',
    'fixed overlay' => '.fi-x { position: fixed; inset: 0; z-index: 999999; }',
    'generated content' => '.fi-x::before { content: "Login expired"; }',
    'hidden input' => 'input[type="hidden"] { display: block; }',
    'CSRF token' => '[name="_token"] { color: red; }',
    'authentication selector' => '.fi-login-page .fi-btn { color: red; }',
    'foreign variable' => '.fi-x { color: var(--private-token); }',
    'unknown property' => '.fi-x { user-select: none; }',
    'unknown function' => '.fi-x { color: device-cmyk(0 1 1 0); }',
    'unknown at-rule' => '@container (width > 1px) { .fi-x { color: red; } }',
    'unclosed string' => '.fi-x { color: "red; }',
    'unclosed comment' => '.fi-x { color: red; /* }',
    'missing brace' => '.fi-x { color: red;',
    'empty rule' => '.fi-x {}',
    'unscoped selector' => '.outside { color: red; }',
    'Unicode selector' => '.fi-bouton-é { color: red; }',
]);

it('rejects oversized CSS and falls back safely for invalid configuration', function () {
    config()->set('filament-theme-studio.custom_css.max_bytes', -1);

    expect(app(CustomCssValidator::class)->validate(str_repeat('a', 50_001))->valid)->toBeFalse();
});

it('returns parser positions without exposing parser exception details', function () {
    $result = app(CustomCssValidator::class)->validate(".fi-btn {\n position: fixed;\n}");

    expect($result->valid)->toBeFalse()
        ->and($result->errors[0]->line)->toBe(2)
        ->and($result->errors[0]->message)->not->toContain('Sabberworm');
});
