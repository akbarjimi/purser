<?php

declare(strict_types=1);

namespace Akbarjimi\Purser\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use RuntimeException;

final class LocalFileResolver
{
    public function resolve(Filesystem $disk, string $path): string
    {
        $direct = $disk->path($path);
        if (is_file($direct)) {
            return $direct;
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $base = tempnam(sys_get_temp_dir(), 'excel_') ?: throw new RuntimeException('Unable to allocate temp file.');
        $temp = $extension !== '' ? "{$base}.{$extension}" : $base;
        if ($temp !== $base) {
            rename($base, $temp);
        }

        $source = $disk->readStream($path);
        if ($source === null) {
            @unlink($temp);
            throw new RuntimeException("Unable to open a read stream for [{$path}].");
        }

        $dest = fopen($temp, 'wb');
        stream_copy_to_stream($source, $dest);
        fclose($dest);
        fclose($source);

        return $temp;
    }
}
