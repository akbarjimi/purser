<?php

declare(strict_types=1);

use Akbarjimi\Purser\Enums\ExcelFileStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('excel_files', function (Blueprint $table) {
            $table->id();

            $table->string('file_name');
            $table->string('path');
            $table->string('disk');
            $table->unsignedBigInteger('size');
            $table->string('status', 32)->default(ExcelFileStatus::PENDING->value)->index();
            $table->json('meta')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error')->nullable();
            $table->string('batch_id')->nullable()->index();

            $table->timestamp('rows_extracted_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('excel_files');
    }
};
