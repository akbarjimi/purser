<?php

declare(strict_types=1);

namespace Akbarjimi\Purser\Services;

use Akbarjimi\Purser\Enums\ExcelRowStatus;
use Akbarjimi\Purser\Models\ExcelRow;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row as SpoutRow;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;

final class ErrorReportService
{
    public const COLUMN_ROW_INDEX = '_row_index';

    public const COLUMN_ERRORS = '_errors';

    /**
     * Paginated broken rows for UI display.
     *
     * @return LengthAwarePaginator<int, ExcelRow>
     */
    public function paginate(int $fileId, int $perPage = 50): LengthAwarePaginator
    {
        return $this->baseQuery($fileId)->paginate($perPage);
    }

    /**
     * All broken rows for a file.
     *
     * @return Collection<int, ExcelRow>
     */
    public function all(int $fileId): Collection
    {
        return $this->baseQuery($fileId)->get();
    }

    /**
     * Broken rows as a JSON string — always available, no optional dependency.
     */
    public function toJson(int $fileId): string
    {
        $payload = $this->all($fileId)
            ->map(fn (ExcelRow $row) => [
                'row_index' => $row->row_index,
                'data' => $row->content,
                'errors' => $row->errors->map(fn ($error) => [
                    'field' => $error->field,
                    'type' => $error->error_type,
                    'code' => $error->error_code,
                    'message' => $error->message,
                ])->all(),
            ])
            ->all();

        return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Write broken rows to a spreadsheet on the given disk. Returns the path on that disk.
     *
     * Requires openspout/openspout (already a suggest for the driver layer).
     *
     * @throws RuntimeException When the writer package is not installed.
     */
    public function toSpreadsheet(int $fileId, string $disk = 'local', ?string $path = null): string
    {
        if (! class_exists(Writer::class)) {
            throw new RuntimeException(
                'Spreadsheet export requires openspout/openspout. Install it with: composer require openspout/openspout'
            );
        }

        $path ??= sprintf(
            'excel-importer/errors/file-%d-%s.xlsx',
            $fileId,
            now()->format('Ymd-His'),
        );

        $temp = tempnam(sys_get_temp_dir(), 'errors_');

        if ($temp === false) {
            throw new RuntimeException('Unable to allocate a temporary file for export.');
        }

        try {
            $this->writeSpreadsheet($this->all($fileId), $temp);
            Storage::disk($disk)->put($path, file_get_contents($temp));
        } finally {
            @unlink($temp);
        }

        return $path;
    }

    /**
     * @return Builder<ExcelRow>
     */
    private function baseQuery(int $fileId): Builder
    {
        return ExcelRow::query()
            ->with('errors')
            ->whereHas('excelSheet', fn (Builder $q) => $q->where('excel_file_id', $fileId))
            ->where('status', ExcelRowStatus::FAILED_VALIDATION->value)
            ->orderBy('id');
    }

    /**
     * @param  Collection<int, ExcelRow>  $rows
     */
    private function writeSpreadsheet(Collection $rows, string $path): void
    {
        $firstRow = $rows->first();
        $dataKeys = $firstRow ? array_keys($firstRow->content ?? []) : [];

        $header = array_merge($dataKeys, [self::COLUMN_ROW_INDEX, self::COLUMN_ERRORS]);

        $writer = new Writer;
        $writer->openToFile($path);

        try {
            $writer->addRow(SpoutRow::fromValues($header));

            foreach ($rows as $row) {
                $data = $row->content ?? [];

                $values = [];
                foreach ($dataKeys as $key) {
                    $values[] = $data[$key] ?? null;
                }

                $values[] = $row->row_index;
                $values[] = $row->errors
                    ->map(fn ($error) => sprintf('[%s] %s', $error->field ?? '-', $error->message))
                    ->implode(' | ');

                $writer->addRow(SpoutRow::fromValues($values));
            }
        } finally {
            $writer->close();
        }
    }
}
