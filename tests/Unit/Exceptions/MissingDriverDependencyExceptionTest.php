<?php

declare(strict_types=1);

use Akbarjimi\ExcelImporter\Exceptions\MissingDriverDependencyException;

it('names the driver, package, and install command in the message', function () {
    $exception = MissingDriverDependencyException::for('openspout', 'openspout/openspout');

    expect($exception)
        ->toBeInstanceOf(RuntimeException::class)
        ->getMessage()->toContain('[openspout]')
        ->getMessage()->toContain('[openspout/openspout]')
        ->getMessage()->toContain('composer require openspout/openspout');
});
