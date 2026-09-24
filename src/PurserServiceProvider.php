<?php

declare(strict_types=1);

namespace Akbarjimi\Purser;

use Akbarjimi\Purser\Console\Commands\BenchmarkCommand;
use Akbarjimi\Purser\Console\Commands\RetryCommand;
use Akbarjimi\Purser\Console\Commands\StatusCommand;
use Akbarjimi\Purser\Contracts\ExcelReaderDriver;
use Akbarjimi\Purser\Events\AllRowsExtracted;
use Akbarjimi\Purser\Events\ExcelFileRegistered;
use Akbarjimi\Purser\Events\FileProcessingCompleted;
use Akbarjimi\Purser\Events\FileSheetsScanCompleted;
use Akbarjimi\Purser\Listeners\HandleAllRowsExtracted;
use Akbarjimi\Purser\Listeners\HandleExcelFileRegistered;
use Akbarjimi\Purser\Listeners\HandleFileSheetsScanCompleted;
use Akbarjimi\Purser\Listeners\InvokeImportHandler;
use Akbarjimi\Purser\Repositories\ExcelRowChunkRepository;
use Akbarjimi\Purser\Repositories\ExcelRowErrorRepository;
use Akbarjimi\Purser\Repositories\ExcelRowRepository;
use Akbarjimi\Purser\Repositories\ExcelSheetRepository;
use Akbarjimi\Purser\Services\ChunkerService;
use Akbarjimi\Purser\Services\ChunkProcessor;
use Akbarjimi\Purser\Services\LocalFileResolver;
use Akbarjimi\Purser\Services\RowExtractionService;
use Akbarjimi\Purser\Services\TransformService;
use Akbarjimi\Purser\Services\ValidateService;
use Akbarjimi\Purser\Support\ExcelReaderManager;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class PurserServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerEventListeners();
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->publishes([
            __DIR__.'/config/purser.php' => config_path('purser.php'),
        ], 'purser');
        $this->publishes([
            __DIR__.'/config/purser-sheets.php' => config_path('purser-sheets.php'),
        ], 'purser-sheets');
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
        $this->mergeConfigFrom(__DIR__.'/config/purser.php', 'purser');
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
                (int) config('purser.insert_batch_size', 100),
                (string) config('purser.hash_algo', 'sha256'),
            );
        });

        $this->app->bind(LocalFileResolver::class);

        $this->app->bind(ChunkerService::class, fn ($app) => new ChunkerService(
            (int) config('purser.chunk_size', 1000),
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
                (int) config('purser.insert_batch_size', 100),
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
