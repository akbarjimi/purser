<?php

namespace Akbarjimi\Purser\Exceptions;

use RuntimeException;

class MissingDriverDependencyException extends RuntimeException
{
    public static function for(string $driver, string $package): self
    {
        return new self(
            "The [{$driver}] Excel reader driver requires the [{$package}] package, which is not installed. "
            ."Install it with: composer require {$package}"
        );
    }
}
