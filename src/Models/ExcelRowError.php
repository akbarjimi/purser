<?php

namespace Akbarjimi\Purser\Models;

use Akbarjimi\Purser\Database\Factories\ExcelRowErrorFactory;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

final class ExcelRowError extends Model implements Arrayable
{
    use HasFactory;

    protected $fillable = [
        'excel_row_id',
        'field',
        'error_type',
        'error_code',
        'message',
    ];

    protected $casts = [
        'field' => 'string',
        'error_type' => 'string',
        'error_code' => 'string',
        'message' => 'string',
    ];

    public function excelRow(): BelongsTo
    {
        return $this->belongsTo(ExcelRow::class, 'excel_row_id');
    }

    public function excelSheet(): HasManyThrough
    {
        return $this->hasManyThrough(
            ExcelSheet::class,
            ExcelRow::class,
            'id',
            'id',
            'excel_row_id',
            'excel_sheet_id',
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'excel_row_id' => $this->excel_row_id,
            'field' => $this->field,
            'error_type' => $this->error_type,
            'error_code' => $this->error_code,
            'message' => $this->message,
            'created_at' => $this->created_at,
        ];
    }

    protected static function newFactory(): ExcelRowErrorFactory
    {
        return ExcelRowErrorFactory::new();
    }
}
