<?php

declare(strict_types=1);

namespace Akbarjimi\Purser\Contracts;

use Akbarjimi\Purser\DTOs\ValidatedRow;

interface ImportHandler
{
    /**
     * @param  int  $fileId  The ID of the imported file.
     * @param  iterable<ValidatedRow>  $rows  Stream of validated rows.
     */
    public function handle(int $fileId, iterable $rows): void;
}
