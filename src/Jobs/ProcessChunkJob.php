<?php

declare(strict_types=1);

namespace Akbarjimi\Purser\Jobs;

use Akbarjimi\Purser\Concerns\LogsImportActivity;
use Akbarjimi\Purser\Services\ChunkProcessor;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;

final class ProcessChunkJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, LogsImportActivity, Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(public readonly int $chunkId) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping("chunk:{$this->chunkId}"))->dontRelease()];
    }

    public function tags(): array
    {
        return ['excel-process', "chunk:{$this->chunkId}"];
    }

    public function handle(ChunkProcessor $processor): void
    {
        $processor->process($this->chunkId);
    }
}
