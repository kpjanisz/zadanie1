<?php

declare(strict_types=1);

namespace App\Product\Exception;

use App\Shared\Exception\TranslatableException;

final class ProductNotFoundException extends \RuntimeException implements TranslatableException
{
    public const string KEY = 'exception.product.not_found';

    /** @var array<string, string|int> */
    private array $parameters;

    public function __construct(int|string $id)
    {
        $this->parameters = ['%id%' => $id];

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
