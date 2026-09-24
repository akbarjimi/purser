<?php

declare(strict_types=1);

namespace Akbarjimi\ExcelImporter;

use Akbarjimi\ExcelImporter\Console\Commands\BenchmarkCommand;
use Akbarjimi\ExcelImporter\Console\Commands\RetryCommand;
use Akbarjimi\ExcelImporter\Console\Commands\StatusCommand;
use Akbarjimi\ExcelImporter\Contracts\ExcelReaderDriver;
use Akbarjimi\ExcelImporter\Events\AllRowsExtracted;
use Akbarjimi\ExcelImporter\Events\ExcelFileRegistered;
use Akbarjimi\ExcelImporter\Events\FileProcessingCompleted;
use Akbarjimi\ExcelImporter\Events\FileSheetsScanCompleted;
use Akbarjimi\ExcelImporter\Listeners\HandleAllRowsExtracted;
use Akbarjimi\ExcelImporter\Listeners\HandleExcelFileRegistered;
use Akbarjimi\ExcelImporter\Listeners\HandleFileSheetsScanCompleted;
use Akbarjimi\ExcelImporter\Listeners\InvokeImportHandler;
use Akbarjimi\ExcelImporter\Repositories\ExcelRowChunkRepository;
use Akbarjimi\ExcelImporter\Repositories\ExcelRowErrorRepository;
use Akbarjimi\ExcelImporter\Repositories\ExcelRowRepository;
use Akbarjimi\ExcelImporter\Repositories\ExcelSheetRepository;
use Akbarjimi\ExcelImporter\Services\ChunkerService;
use Akbarjimi\ExcelImporter\Services\ChunkProcessor;
use Akbarjimi\ExcelImporter\Services\LocalFileResolver;
use Akbarjimi\ExcelImporter\Services\RowExtractionService;
use Akbarjimi\ExcelImporter\Services\TransformService;
use Akbarjimi\ExcelImporter\Services\ValidateService;
use Akbarjimi\ExcelImporter\Support\ExcelReaderManager;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class ExcelImporterServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerEventListeners();
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->publishes([
            __DIR__.'/config/excel-importer.php' => config_path('excel-importer.php'),
        ], 'excel-importer');
        $this->publishes([
            __DIR__.'/config/excel-importer-sheets.php' => config_path('excel-importer-sheets.php'),
        ], 'excel-importer-sheets');
        if ($this->app->runningInConsole()) {
            $this->commands([
                StatusCommand::class,
                RetryCommand::class,
                BenchmarkCommand::class,
            ]);
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config/excel-importer.php', 'excel-importer');
        $this->loadFactoriesFrom(__DIR__.'/database/factories');

        $this->app->singleton(ExcelReaderManager::class);
        $this->app->bind(ExcelReaderDriver::class, fn ($app) => $app->make(ExcelReaderManager::class)->driver());

        $this->app->bind(RowExtractionService::class, function ($app) {
            return new RowExtractionService(
                $app->make(ExcelReaderDriver::class),
                $app->make(ExcelRowRepository::class),
                $app->make(ExcelSheetRepository::class),
                $app->make(Factory::class),
                $app->make(LocalFileResolver::class),
                (int) config('excel-importer.insert_batch_size', 100),
                (string) config('excel-importer.hash_algo', 'sha256'),
            );
        });

        $this->app->bind(LocalFileResolver::class);

        $this->app->bind(ChunkerService::class, fn ($app) => new ChunkerService(
            (int) config('excel-importer.chunk_size', 1000),
            $app->make(ExcelRowRepository::class),
            $app->make(ExcelRowChunkRepository::class),
            $app->make(ExcelSheetRepository::class),
        ));

        $this->app->bind(ChunkProcessor::class, function ($app) {
            return new ChunkProcessor(
                $app->make(ExcelRowRepository::class),
                $app->make(ExcelRowChunkRepository::class),
                $app->make(ExcelRowErrorRepository::class),
                $app->make(ExcelSheetRepository::class),
                $app->make(TransformService::class),
                $app->make(ValidateService::class),
                (int) config('excel-importer.insert_batch_size', 100),
            );
        });
    }

    public function registerEventListeners(): void
    {
        Event::listen(ExcelFileRegistered::class, HandleExcelFileRegistered::class);
        Event::listen(FileSheetsScanCompleted::class, HandleFileSheetsScanCompleted::class);
        Event::listen(AllRowsExtracted::class, HandleAllRowsExtracted::class);
        Event::listen(FileProcessingCompleted::class, InvokeImportHandler::class);
    }
}
