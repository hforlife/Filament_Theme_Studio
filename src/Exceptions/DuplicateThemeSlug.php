<?php

declare(strict_types=1);

namespace Hforlife\FilamentThemeStudio\Exceptions;

use DomainException;

class DuplicateThemeSlug extends DomainException
{
    public static function forPanel(): self
    {
        return new self('A theme with this slug already exists for the panel.');
    }
}
