<?php

declare(strict_types=1);

namespace Akbarjimi\Purser\Services;

use Akbarjimi\Purser\Repositories\ExcelFileRepository;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;

final class ImportManager
{
    public function __construct(
        private Config $config,
        private FilesystemFactory $storageFactory,
        private ExcelFileRepository $fileRepo,
    ) {}

    public function import(string $path, ?string $disk = null): PendingImport
    {
        $disk = $disk
            ?? $this->config->get('purser.default_disk')
            ?? $this->config->get('filesystems.default')
            ?? 'local';

        return new PendingImport(
            $path,
            $disk,
            $this->storageFactory,
            $this->fileRepo,
        );
    }
}
