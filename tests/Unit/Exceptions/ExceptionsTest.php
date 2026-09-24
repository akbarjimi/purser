<?php

declare(strict_types=1);

namespace Akbarjimi\Purser\Tests\Unit\Exceptions;

use Akbarjimi\Purser\Exceptions\ImportException;
use Akbarjimi\Purser\Exceptions\ImportFileNotFoundException;
use Akbarjimi\Purser\Exceptions\MissingHandlerException;
use Akbarjimi\Purser\Exceptions\Sheet\EmptySheetException;
use Akbarjimi\Purser\Exceptions\Sheet\SheetNotFoundException;

/**
 * Test all custom exceptions.
 *
 * Ensures exceptions are thrown with correct messages and types.
 *
 * @group exceptions
 */
describe('Exceptions', function () {
    it('ImportFileNotFoundException throws with correct message', function () {
        $exception = ImportFileNotFoundException::make('s3', 'path/file.xlsx');

        expect($exception)
            ->toBeInstanceOf(ImportException::class)
            ->getMessage()->toContain('path/file.xlsx');
    });

    it('EmptySheetException throws with correct message', function () {
        $exception = EmptySheetException::forFile(123);

        expect($exception)
            ->toBeInstanceOf(ImportException::class)
            ->getMessage()->toContain('123');
    });

    it('SheetNotFoundException throws with correct message', function () {
        $exception = new SheetNotFoundException('Sheet not found');

        expect($exception)
            ->toBeInstanceOf(ImportException::class)
            ->getMessage()->toBe('Sheet not found');
    });

    it('MissingHandlerException (alias) throws with correct message', function () {
        $exception = MissingHandlerException::make();

        expect($exception)
            ->toBeInstanceOf(ImportException::class)
            ->getMessage()->toContain('handler');
    });

    it('all exceptions extend ImportException', function () {
        expect(ImportFileNotFoundException::make('local', 'file.xlsx'))
            ->toBeInstanceOf(ImportException::class)
            ->and(EmptySheetException::forFile(1))
            ->toBeInstanceOf(ImportException::class)
            ->and(new SheetNotFoundException('msg'))
            ->toBeInstanceOf(ImportException::class)
            ->and(MissingHandlerException::make())
            ->toBeInstanceOf(ImportException::class);
    });
});
