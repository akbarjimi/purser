<?php

declare(strict_types=1);

namespace Akbarjimi\Purser\Services;

use Akbarjimi\Purser\Contracts\ExcelReaderDriver;
use Akbarjimi\Purser\Contracts\SheetDiscoveryInterface;
use Akbarjimi\Purser\Models\ExcelFile;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;

final class SheetDiscoveryService implements SheetDiscoveryInterface
{
    public function __construct(
        private readonly FilesystemFactory $storage,
        private readonly ExcelReaderDriver $readerDriver,
        private readonly LocalFileResolver $fileResolver,
    ) {}

    public function discover(ExcelFile $file): array
    {
        $disk = $this->storage->disk($file->disk);
        $localPath = $this->fileResolver->resolve($disk, $file->path);
        $isTemp = $localPath !== $disk->path($file->path);

        try {
            return $this->readerDriver->listSheets($localPath);
        } finally {
            if ($isTemp && is_file($localPath)) {
                @unlink($localPath);
            }
        }
    }
}
