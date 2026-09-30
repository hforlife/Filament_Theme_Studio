<?php

declare(strict_types=1);

namespace Hforlife\FilamentThemeStudio\Exceptions;

use InvalidArgumentException;

class InvalidThemeSettings extends InvalidArgumentException
{
    public static function invalidVersionLimit(): self
    {
        return new self('The theme version limit must be an integer greater than zero.');
    }
}
