<?php

declare(strict_types=1);

namespace Akbarjimi\Purser\Services;

use Akbarjimi\Purser\Contracts\ImportHandler;
use Akbarjimi\Purser\Enums\ExcelFileStatus;
use Akbarjimi\Purser\Events\ExcelFileRegistered;
use Akbarjimi\Purser\Exceptions\ImportFileNotFoundException;
use Akbarjimi\Purser\Exceptions\MissingHandlerException;
use Akbarjimi\Purser\Models\ExcelFile;
use Akbarjimi\Purser\Repositories\ExcelFileRepository;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Support\Facades\DB;

final class PendingImport
{
    private string $handler;

    private array $meta = [];

    public function __construct(
        private readonly string $path,
        private readonly string $disk,
        private readonly FilesystemFactory $storageFactory,
        private readonly ExcelFileRepository $fileRepo,
    ) {}

    public function withHandler(string $handlerClass): self
    {
        if (! is_a($handlerClass, ImportHandler::class, true)) {
            throw new \InvalidArgumentException(
                sprintf('Handler must implement [%s]', ImportHandler::class)
            );
        }

        $this->handler = $handlerClass;

        return $this;
    }

    public function withMeta(array $meta): self
    {
        $this->meta = $meta;

        return $this;
    }

    public function dispatch(): ExcelFile
    {
        if (! isset($this->handler)) {
            throw MissingHandlerException::make();
        }

        $storage = $this->storageFactory->disk($this->disk);

        if (! $storage->exists($this->path)) {
            throw ImportFileNotFoundException::make($this->disk, $this->path);
        }

        return DB::transaction(function () use ($storage) {
            $data = [
                'file_name' => basename($this->path),
                'path' => $this->path,
                'disk' => $this->disk,
                'size' => $storage->size($this->path),
                'meta' => array_merge($this->meta, ['handler' => $this->handler]),
                'status' => ExcelFileStatus::PENDING,
            ];

            $file = $this->fileRepo->create($data);

            ExcelFileRegistered::dispatch($file->id);

            return $file;
        });
    }
}
