<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared;

use App\Category\Exception\CategoryCodeAlreadyExistsException;
use App\Category\Exception\CategoryInUseException;
use App\Category\Exception\CategoryNotFoundException;
use App\Notification\Exception\OperationLogNotFoundException;
use App\Product\Exception\ProductNotFoundException;
use App\Product\Exception\ProductRequiresCategoryException;
use App\Shared\Exception\PreconditionFailedException;
use App\Shared\Exception\TranslatableException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Every message that reaches a client comes from a catalogue.
 *
 * The RFC 7807 `detail` is user-facing text, so a literal English sentence in an
 * exception is the same defect as a literal in a template — and it went
 * unnoticed for several rounds, which is why it is asserted now.
 */
#[CoversNothing]
final class TranslatableExceptionTest extends TestCase
{
    #[DataProvider('domainExceptions')]
    public function testCarriesAKeyThatExistsInEveryCatalogue(TranslatableException $exception): void
    {
        $key = $exception->getTranslationKey();

        self::assertSame($key, $exception->getMessage(), 'The raw message should be the key, not a sentence.');
        self::assertMatchesRegularExpression('/^exception(\.[a-z_]+){1,2}$/', $key);

        foreach (['pl', 'en'] as $locale) {
            self::assertNotNull(
                $this->lookup($locale, $key),
                \sprintf('Key "%s" is missing from the %s catalogue.', $key, $locale),
            );
        }
    }

    #[DataProvider('domainExceptions')]
    public function testEveryPlaceholderItDeclaresIsUsedByBothTranslations(TranslatableException $exception): void
    {
        foreach (['pl', 'en'] as $locale) {
            $text = $this->lookup($locale, $exception->getTranslationKey());
            self::assertIsString($text);

            foreach (array_keys($exception->getTranslationParameters()) as $placeholder) {
                self::assertStringContainsString(
                    (string) $placeholder,
                    $text,
                    \sprintf('The %s translation ignores %s.', $locale, $placeholder),
                );
            }
        }
    }

    /**
     * @return iterable<string, array{TranslatableException}>
     */
    public static function domainExceptions(): iterable
    {
        yield 'category not found' => [new CategoryNotFoundException(7)];
        yield 'category code taken' => [new CategoryCodeAlreadyExistsException('AGD')];
        yield 'category in use' => [new CategoryInUseException(7, 3)];
        yield 'product not found' => [new ProductNotFoundException(7)];
        yield 'product requires category' => [new ProductRequiresCategoryException()];
        yield 'precondition failed' => [new PreconditionFailedException()];
        yield 'operation log not found' => [new OperationLogNotFoundException(7)];
    }

    private function lookup(string $locale, string $key): ?string
    {
        /** @var array<string, mixed> $catalogue */
        $catalogue = Yaml::parseFile(\dirname(__DIR__, 3).'/translations/messages.'.$locale.'.yaml');

        $node = $catalogue;
        foreach (explode('.', $key) as $segment) {
            if (!\is_array($node) || !\array_key_exists($segment, $node)) {
                return null;
            }
            $node = $node[$segment];
        }

        return \is_string($node) ? $node : null;
    }
}
