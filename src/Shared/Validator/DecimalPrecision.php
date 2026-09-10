<?php

declare(strict_types=1);

namespace App\Shared\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Asserts that a value fits a SQL DECIMAL(precision, scale) column.
 *
 * Exists because none of the built-in constraints can express "at most two
 * decimal places" for a value that may legitimately arrive as either a JSON
 * string ("1299.50") or a JSON number (1299.5): Assert\Regex refuses non-strings,
 * and Assert\DivisibleBy would do the check in binary floating point — the very
 * arithmetic DECIMAL is chosen to avoid.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::IS_REPEATABLE)]
final class DecimalPrecision extends Constraint
{
    public string $notNumericMessage = 'validator.decimal.not_numeric';
    public string $scaleMessage = 'validator.decimal.scale';
    public string $precisionMessage = 'validator.decimal.precision';

    public function __construct(
        public int $precision = 12,
        public int $scale = 2,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct([], $groups, $payload);
    }
}
