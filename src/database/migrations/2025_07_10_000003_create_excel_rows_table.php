<?php

use Akbarjimi\ExcelImporter\Enums\ExcelRowStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('excel_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('excel_sheet_id')
                ->constrained()
                ->onDelete('cascade')
                ->index();
            $table->unsignedInteger('row_index')->nullable();
            $table->json('content');
            $table->string('hash_algo')->default('md5');
            $table->string('content_hash')->nullable()->index();
            $table->string('status', 32)->default(ExcelRowStatus::PENDING->value)->index();
            $table->unsignedInteger('chunk_index')->nullable()->index();
            $table->softDeletes();
            $table->timestamps();

            // Uniqueness is by sheet position so duplicate cell values remain distinct rows.
            // content_hash stays as a non-unique index for change detection / diagnostics.
            $table->unique(['excel_sheet_id', 'row_index'], 'sheet_row_index_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('excel_rows');
    }
};
