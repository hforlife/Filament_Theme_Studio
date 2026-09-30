<?php

declare(strict_types=1);

namespace Hforlife\FilamentThemeStudio\Exceptions;

use DomainException;

class ThemeVersionNotFound extends DomainException
{
    public static function forTheme(): self
    {
        return new self('The requested theme version does not belong to this theme.');
    }
}
