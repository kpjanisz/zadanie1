<?php

declare(strict_types=1);

namespace App\Shared\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class DecimalPrecisionValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof DecimalPrecision) {
            throw new UnexpectedTypeException($constraint, DecimalPrecision::class);
        }

        // Absence is NotNull's business, not this constraint's.
        if (null === $value || '' === $value) {
            return;
        }

        // Booleans are scalars but not decimals, and (string) true would sail
        // through the pattern below as "1".
        if (!\is_string($value) && !\is_int($value) && !\is_float($value)) {
            $this->context->buildViolation($constraint->notNumericMessage)->addViolation();

            return;
        }

        // Comparison happens on the decimal string, never on a float.
        $string = (string) $value;

        if (1 !== preg_match('/^\d+(\.\d+)?$/', $string)) {
            $this->context->buildViolation($constraint->notNumericMessage)->addViolation();

            return;
        }

        [$integerPart, $decimalPart] = array_pad(explode('.', $string, 2), 2, '');

        if (\strlen($decimalPart) > $constraint->scale) {
            $this->context->buildViolation($constraint->scaleMessage)
                ->setParameter('{{ scale }}', (string) $constraint->scale)
                ->addViolation();

            return;
        }

        $significantDigits = \strlen(ltrim($integerPart, '0'));

        if ($significantDigits > $constraint->precision - $constraint->scale) {
            $this->context->buildViolation($constraint->precisionMessage)
                ->setParameter('{{ digits }}', (string) ($constraint->precision - $constraint->scale))
                ->addViolation();
        }
    }
}
