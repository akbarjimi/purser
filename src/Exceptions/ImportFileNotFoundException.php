<?php

declare(strict_types=1);

namespace Akbarjimi\Purser\Exceptions;

final class ImportFileNotFoundException extends ImportException
{
    public static function make(string $disk, string $path): self
    {
        return new self("Excel file [{$path}] not found on disk [{$disk}].");
    }
}
