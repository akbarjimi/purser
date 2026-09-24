<?php

declare(strict_types=1);

namespace Akbarjimi\Purser\Tests\Unit\Services;

use Akbarjimi\Purser\Services\ImportManager;
use Akbarjimi\Purser\Services\PendingImport;

/**
 * Tests the ImportManager entry point.
 *
 * @group services
 * @group entry
 */
describe('ImportManager', function () {
    it('creates a PendingImport with given path and disk', function () {
        $manager = app(ImportManager::class);
        $pending = $manager->import('test.xlsx', 'local');

        expect($pending)->toBeInstanceOf(PendingImport::class);
    });

    it('uses default disk from config when not provided', function () {
        config(['purser.default_disk' => 's3']);
        config(['filesystems.default' => 'local']);

        $manager = app(ImportManager::class);
        $pending = $manager->import('test.xlsx');

        $reflection = new \ReflectionClass($pending);
        $property = $reflection->getProperty('disk');
        $property->setAccessible(true);
        expect($property->getValue($pending))->toBe('s3');
    });

    it('falls back to filesystems.default if package config missing', function () {
        config(['purser.default_disk' => null]);
        config(['filesystems.default' => 'local']);

        $manager = app(ImportManager::class);
        $pending = $manager->import('test.xlsx');

        $reflection = new \ReflectionClass($pending);
        $property = $reflection->getProperty('disk');
        $property->setAccessible(true);
        expect($property->getValue($pending))->toBe('local');
    });
});
