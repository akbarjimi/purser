<?php

declare(strict_types=1);

namespace Akbarjimi\Purser\Tests\Unit\Services;

use Akbarjimi\Purser\Models\ExcelSheet;
use Akbarjimi\Purser\Services\ValidateService;

/**
 * @group services
 * @group transform-validate
 */
describe('ValidateService', function () {
    it('validates row based on config rules', function () {
        config(['purser-sheets' => [
            'Users' => [
                'validation' => [
                    'name' => 'required|string|max:10',
                    'email' => 'required|email',
                ],
            ],
        ]]);

        $sheet = ExcelSheet::factory()->make(['name' => 'Users']);
        $row = ['name' => 'John', 'email' => 'john@example.com'];

        $service = new ValidateService(app('config'));
        $errors = $service->apply($row, $sheet);

        expect($errors)->toBeEmpty();
    });

    it('returns validation errors when invalid', function () {
        config(['purser-sheets' => [
            'Users' => [
                'validation' => [
                    'name' => 'required|string|max:10',
                    'email' => 'required|email',
                ],
            ],
        ]]);

        $sheet = ExcelSheet::factory()->make(['name' => 'Users']);
        $row = ['name' => '', 'email' => 'not-an-email'];

        $service = new ValidateService(app('config'));
        $errors = $service->apply($row, $sheet);

        expect($errors)->not->toBeEmpty();
        expect($errors)->toHaveKeys(['name', 'email']);
    });

    it('returns empty array when no validation rules', function () {
        config(['purser-sheets' => [
            'Users' => [],
        ]]);

        $sheet = ExcelSheet::factory()->make(['name' => 'Users']);
        $row = ['name' => 'John'];

        $service = new ValidateService(app('config'));
        $errors = $service->apply($row, $sheet);

        expect($errors)->toBeEmpty();
    });

    it('throws exception in strict mode with no rules', function () {
        config(['purser-sheets' => [
            'Users' => [],
        ]]);
        config(['purser.strict_validation' => true]);

        $sheet = ExcelSheet::factory()->make(['name' => 'Users']);
        $row = ['name' => 'John'];

        $service = new ValidateService(app('config'));
        $service->apply($row, $sheet);
    })->throws(\RuntimeException::class, 'No validation rules');

    it('returns empty array when validation passes', function () {
        config(['purser-sheets.Orders.validation' => ['name' => 'required']]);
        $sheet = ExcelSheet::factory()->make(['name' => 'Orders']);
        $service = new ValidateService(app('config'));
        $errors = $service->apply(['name' => 'John'], $sheet);
        expect($errors)->toBeEmpty();
    });

    it('returns errors when validation fails', function () {
        config(['purser-sheets.Orders.validation' => ['name' => 'required']]);
        $sheet = ExcelSheet::factory()->make(['name' => 'Orders']);
        $service = new ValidateService(app('config'));
        $errors = $service->apply(['name' => ''], $sheet);
        expect($errors)->toHaveKey('name');
    });
});
