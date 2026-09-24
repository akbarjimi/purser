<?php

declare(strict_types=1);

namespace Akbarjimi\Purser\Models;

use Akbarjimi\Purser\Database\Factories\ExcelRowChunkFactory;
use Akbarjimi\Purser\Enums\ExcelChunkStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ExcelRowChunk extends Model
{
    use HasFactory;

    protected $fillable = [
        'excel_sheet_id',
        'from_row_id',
        'to_row_id',
        'size',
        'status',
        'attempts',
        'error',
        'dispatched_at',
        'processed_at',
    ];

    protected $casts = [
        'status' => ExcelChunkStatus::class,
        'size' => 'integer',
        'attempts' => 'integer',
        'dispatched_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function excelSheet(): BelongsTo
    {
        return $this->belongsTo(ExcelSheet::class, 'excel_sheet_id')->withTrashed();
    }

    protected static function newFactory(): ExcelRowChunkFactory
    {
        return ExcelRowChunkFactory::new();
    }
}
