<?php

declare(strict_types=1);

namespace App\Category\Exception;

use App\Shared\Exception\TranslatableException;

final class CategoryCodeAlreadyExistsException extends \RuntimeException implements TranslatableException
{
    public const string KEY = 'exception.category.code_taken';

    /** @var array<string, string|int> */
    private array $parameters;

    public function __construct(string $code, ?\Throwable $previous = null)
    {
        $this->parameters = ['%code%' => $code];

        parent::__construct(self::KEY, previous: $previous);
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
