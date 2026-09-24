<?php

declare(strict_types=1);

namespace Akbarjimi\Purser\Exceptions\Sheet;

use Akbarjimi\Purser\Exceptions\ImportException;

final class EmptySheetException extends ImportException
{
    public static function forFile(int $fileId): self
    {
        return new self(sprintf('No sheets discovered for Excel file ID [%d].', $fileId));
    }
}
