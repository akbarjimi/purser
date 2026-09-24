<?php

use Akbarjimi\Purser\Enums\ExcelRowStatus;
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
            $table->string('content_hash')->nullable();
            $table->string('status', 32)->default(ExcelRowStatus::PENDING->value)->index();
            $table->unsignedInteger('chunk_index')->nullable()->index();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['excel_sheet_id', 'content_hash', 'hash_algo'], 'sheet_content_hash_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('excel_rows');
    }
};
