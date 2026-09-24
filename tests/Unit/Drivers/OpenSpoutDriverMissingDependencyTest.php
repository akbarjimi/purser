<?php

declare(strict_types=1);

use Akbarjimi\Purser\Contracts\RowHandler;
use Akbarjimi\Purser\Drivers\OpenSpoutDriver;
use Akbarjimi\Purser\DTOs\RowData;
use Akbarjimi\Purser\Exceptions\MissingDriverDependencyException;
use OpenSpout\Reader\XLSX\Reader;

it('throws MissingDriverDependencyException from readRows when openspout is absent', function () {
    if (class_exists(Reader::class)) {
        $this->markTestSkipped('openspout/openspout is installed; guard cannot be exercised.');
    }

    $handler = new class implements RowHandler
    {
        public function handle(RowData $row): void {}
    };

    expect(fn () => (new OpenSpoutDriver)->readRows('/tmp/x.xlsx', 0, $handler))
        ->toThrow(MissingDriverDependencyException::class);
});

it('throws MissingDriverDependencyException from listSheets when openspout is absent', function () {
    if (class_exists(Reader::class)) {
        $this->markTestSkipped('openspout/openspout is installed; guard cannot be exercised.');
    }

    expect(fn () => (new OpenSpoutDriver)->listSheets('/tmp/x.xlsx'))
        ->toThrow(MissingDriverDependencyException::class);
});
