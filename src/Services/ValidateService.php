<?php

declare(strict_types=1);

namespace Akbarjimi\Purser\Services;

use Akbarjimi\Purser\Contracts\ValidatorInterface;
use Akbarjimi\Purser\Models\ExcelSheet;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Facades\Validator;

final class ValidateService implements ValidatorInterface
{
    public function __construct(private Config $config) {}

    public function apply(array $payload, ExcelSheet $sheet): array
    {
        $rules = $this->config->get("purser-sheets.{$sheet->name}.validation", []);
        if (empty($rules)) {
            if ($this->config->get('purser.strict_validation', false)) {
                throw new \RuntimeException("No validation rules for sheet [{$sheet->name}].");
            }

            return [];
        }
        $validator = Validator::make($payload, $rules);

        return $validator->fails() ? $validator->errors()->toArray() : [];
    }
}
