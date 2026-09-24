<?php

declare(strict_types=1);

namespace Akbarjimi\Purser\Exceptions;

final class MissingHandlerException extends ImportException
{
    public static function make(): self
    {
        return new self('No handler set for import. Call withHandler() before dispatch().');
    }
}
