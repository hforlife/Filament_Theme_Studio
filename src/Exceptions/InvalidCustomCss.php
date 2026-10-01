<?php

declare(strict_types=1);

namespace Hforlife\FilamentThemeStudio\Exceptions;

use Hforlife\FilamentThemeStudio\Support\CustomCssValidationResult;
use RuntimeException;

final class InvalidCustomCss extends RuntimeException
{
    public static function fromResult(CustomCssValidationResult $result): self
    {
        return new self($result->errors[0]->message ?? 'The custom CSS is invalid.');
    }
}
