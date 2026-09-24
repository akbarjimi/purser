<?php

declare(strict_types=1);

use Akbarjimi\Purser\Enums\ExcelSheetStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('excel_sheets', static function (Blueprint $table): void {
            $table->id();

            $table->foreignId('excel_file_id')->constrained()->onDelete('cascade');

            $table->string('name');
            $table->unsignedTinyInteger('sheet_index');
            $table->unsignedInteger('total_rows');

            $table->unsignedInteger('chunk_count')->default(0);
            $table->unsignedInteger('processed_chunks')->default(0);

            $table->string('status', 32)->default(ExcelSheetStatus::PENDING->value)->index();
            $table->json('meta')->nullable();
            $table->timestamp('rows_extracted_at')->nullable();

            $table->unique(['excel_file_id', 'sheet_index']);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('excel_sheets');
    }
};
