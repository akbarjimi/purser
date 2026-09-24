<?php

declare(strict_types=1);

namespace Akbarjimi\Purser\Services;

use Akbarjimi\Purser\Contracts\TransformerInterface;
use Akbarjimi\Purser\Models\ExcelSheet;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;

final class TransformService
{
    public function __construct(
        private readonly Config $config,
        private readonly Container $container,
    ) {}

    public function apply(array $rawRow, ExcelSheet $sheet): array
    {
        $sheetConfig = $this->config->get("purser-sheets.{$sheet->name}", []);

        $mappedRow = $this->applyMapping($rawRow, $sheetConfig['mapping'] ?? []);

        $transformerClass = $sheetConfig['transformer'] ?? null;

        if ($transformerClass === null) {
            return $mappedRow;
        }

        $transformer = $this->container->make($transformerClass);

        if (! $transformer instanceof TransformerInterface) {
            throw new \RuntimeException(sprintf(
                'Transformer [%s] must implement [%s].',
                $transformerClass,
                TransformerInterface::class,
            ));
        }

        return $transformer->transform($mappedRow, $sheet);
    }

    private function applyMapping(array $rawRow, array $mapping): array
    {
        if ($mapping === []) {
            return array_filter(
                $rawRow,
                static fn (string|int $key): bool => ! is_string($key) || ! str_starts_with($key, '_'),
                ARRAY_FILTER_USE_KEY,
            );
        }

        $mapped = [];

        foreach ($mapping as $targetKey => $sourceKey) {
            if (str_starts_with((string) $targetKey, '_')) {
                continue;
            }

            $mapped[$targetKey] = $rawRow[$sourceKey] ?? null;
        }

        return $mapped;
    }
}
