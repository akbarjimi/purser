<?php

declare(strict_types=1);

namespace Akbarjimi\Purser\Models;

use Akbarjimi\Purser\Database\Factories\ExcelSheetFactory;
use Akbarjimi\Purser\Enums\ExcelSheetStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class ExcelSheet extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'excel_file_id',
        'name',
        'sheet_index',
        'total_rows',
        'chunk_count',
        'processed_chunks',
        'status',
        'meta',
        'rows_extracted_at',
    ];

    protected $casts = [
        'status' => ExcelSheetStatus::class,
        'meta' => 'array',
        'rows_extracted_at' => 'datetime',
        'total_rows' => 'integer',
        'chunk_count' => 'integer',
        'processed_chunks' => 'integer',
    ];

    public function excelFile(): BelongsTo
    {
        return $this->belongsTo(ExcelFile::class)->withTrashed();
    }

    public function excelRows(): HasMany
    {
        return $this->hasMany(ExcelRow::class);
    }

    protected static function newFactory(): ExcelSheetFactory
    {
        return ExcelSheetFactory::new();
    }
}
