<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Existing installs unique-keyed rows by content hash, which silently dropped
 * duplicate spreadsheet rows. Fresh installs already get sheet+row_index from
 * the create migration; this migration only rewrites indexes when the old
 * unique is still present.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('excel_rows')) {
            return;
        }

        $indexNames = collect(Schema::getIndexes('excel_rows'))->pluck('name');

        if ($indexNames->contains('sheet_content_hash_unique')) {
            Schema::table('excel_rows', function (Blueprint $table) {
                $table->dropUnique('sheet_content_hash_unique');
            });
        }

        $indexNames = collect(Schema::getIndexes('excel_rows'))->pluck('name');

        if (! $indexNames->contains('excel_rows_content_hash_index')) {
            Schema::table('excel_rows', function (Blueprint $table) {
                $table->index('content_hash');
            });
        }

        if (! $indexNames->contains('sheet_row_index_unique')) {
            Schema::table('excel_rows', function (Blueprint $table) {
                $table->unique(['excel_sheet_id', 'row_index'], 'sheet_row_index_unique');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('excel_rows')) {
            return;
        }

        $indexNames = collect(Schema::getIndexes('excel_rows'))->pluck('name');

        if ($indexNames->contains('sheet_row_index_unique')) {
            Schema::table('excel_rows', function (Blueprint $table) {
                $table->dropUnique('sheet_row_index_unique');
            });
        }

        $indexNames = collect(Schema::getIndexes('excel_rows'))->pluck('name');

        if ($indexNames->contains('excel_rows_content_hash_index')) {
            Schema::table('excel_rows', function (Blueprint $table) {
                $table->dropIndex(['content_hash']);
            });
        }

        if (! $indexNames->contains('sheet_content_hash_unique')) {
            Schema::table('excel_rows', function (Blueprint $table) {
                $table->unique(['excel_sheet_id', 'content_hash', 'hash_algo'], 'sheet_content_hash_unique');
            });
        }
    }
};
