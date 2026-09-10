<?php

declare(strict_types=1);

namespace App\Product\Exception;

use App\Shared\Exception\TranslatableException;

/**
 * Last line of defence for "a product belongs to at least one category".
 *
 * The rule is expressed as Assert\Count on the input DTOs; MySQL cannot enforce
 * it on a join table, so the processor re-checks before flush.
 */
final class ProductRequiresCategoryException extends \RuntimeException implements TranslatableException
{
    public const string KEY = 'exception.product.requires_category';

    public function __construct()
    {
        parent::__construct(self::KEY);
    }

    public function getTranslationKey(): string
    {
        return self::KEY;
    }

    public function getTranslationParameters(): array
    {
        return [];
    }
}
