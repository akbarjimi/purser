<?php

declare(strict_types=1);

namespace Akbarjimi\Purser\Contracts;

use Akbarjimi\Purser\Models\ExcelFile;
use Akbarjimi\Purser\Models\ExcelRowChunk;
use Illuminate\Support\Collection;

interface ChunkerInterface
{
    /**
     * @return Collection<int, ExcelRowChunk>
     */
    public function createChunksForFile(ExcelFile $file): Collection;
}
