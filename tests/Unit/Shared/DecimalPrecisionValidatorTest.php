<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared;

use App\Shared\Validator\DecimalPrecision;
use App\Shared\Validator\DecimalPrecisionValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<DecimalPrecisionValidator>
 */
#[CoversClass(DecimalPrecision::class)]
#[CoversClass(DecimalPrecisionValidator::class)]
final class DecimalPrecisionValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): DecimalPrecisionValidator
    {
        return new DecimalPrecisionValidator();
    }

    #[DataProvider('acceptedValues')]
    public function testAcceptsValuesThatFitTheColumn(string|int|float|null $value): void
    {
        $this->validator->validate($value, new DecimalPrecision(precision: 12, scale: 2));

        $this->assertNoViolation();
    }

    /**
     * @return iterable<string, array{string|int|float|null}>
     */
    public static function acceptedValues(): iterable
    {
        yield 'null is NotNull\'s business' => [null];
        yield 'empty string' => [''];
        yield 'integer string' => ['1299'];
        yield 'one decimal' => ['1299.5'];
        yield 'two decimals' => ['1299.50'];
        yield 'zero' => ['0'];
        yield 'ten integer digits' => ['9999999999.99'];
        yield 'leading zeros do not count' => ['0000000000001.99'];
        yield 'a JSON number still validates' => [1299.5];
        yield 'an integer still validates' => [1299];
    }

    public function testRejectsMoreDecimalsThanTheScale(): void
    {
        $this->validator->validate('19.999', new DecimalPrecision(precision: 12, scale: 2));

        $this->buildViolation('validator.decimal.scale')
            ->setParameter('{{ scale }}', '2')
            ->assertRaised();
    }

    public function testRejectsMoreIntegerDigitsThanThePrecisionAllows(): void
    {
        $this->validator->validate('12345678901.00', new DecimalPrecision(precision: 12, scale: 2));

        $this->buildViolation('validator.decimal.precision')
            ->setParameter('{{ digits }}', '10')
            ->assertRaised();
    }

    #[DataProvider('nonNumericValues')]
    public function testRejectsAnythingThatIsNotADecimal(mixed $value): void
    {
        $this->validator->validate($value, new DecimalPrecision());

        $this->buildViolation('validator.decimal.not_numeric')->assertRaised();
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function nonNumericValues(): iterable
    {
        yield 'letters' => ['abc'];
        yield 'negative' => ['-5.00'];
        yield 'scientific notation' => ['1.0E+25'];
        yield 'comma as separator' => ['1299,50'];
        yield 'boolean' => [true];
        yield 'array' => [[1299]];
        yield 'whitespace' => [' 1299.50 '];
    }

    public function testRefusesToRunAgainstTheWrongConstraint(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate('1.00', new \Symfony\Component\Validator\Constraints\NotBlank());
    }
}
