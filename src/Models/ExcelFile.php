<?php

declare(strict_types=1);

namespace Akbarjimi\Purser\Models;

use Akbarjimi\Purser\Database\Factories\ExcelFileFactory;
use Akbarjimi\Purser\Enums\ExcelFileStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class ExcelFile extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'file_name',
        'path',
        'disk',
        'size',
        'status',
        'meta',
        'completed_at',
        'rows_extracted_at',
        'error',
        'batch_id',
    ];

    protected $casts = [
        'status' => ExcelFileStatus::class,
        'size' => 'integer',
        'meta' => 'array',
        'completed_at' => 'datetime',
        'rows_extracted_at' => 'datetime',
    ];

    public function excelSheets(): HasMany
    {
        return $this->hasMany(ExcelSheet::class);
    }

    protected static function newFactory(): ExcelFileFactory
    {
        return ExcelFileFactory::new();
    }
}
