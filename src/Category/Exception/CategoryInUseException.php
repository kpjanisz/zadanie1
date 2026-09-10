<?php

declare(strict_types=1);

namespace App\Category\Exception;

use App\Shared\Exception\TranslatableException;

/**
 * Deleting a category that still has products would leave those products with no
 * category, which the domain forbids.
 */
final class CategoryInUseException extends \RuntimeException implements TranslatableException
{
    public const string KEY = 'exception.category.in_use';

    /** @var array<string, string|int> */
    private array $parameters;

    public function __construct(int $id, int $productCount)
    {
        $this->parameters = ['%id%' => $id, '%count%' => $productCount];

        parent::__construct(self::KEY);
    }

    public function getTranslationKey(): string
    {
        return self::KEY;
    }

    public function getTranslationParameters(): array
    {
        return $this->parameters;
    }
}
