<?php

declare(strict_types=1);

namespace App\Notification\Exception;

use App\Shared\Exception\TranslatableException;

final class OperationLogNotFoundException extends \RuntimeException implements TranslatableException
{
    public const string KEY = 'exception.operation_log.not_found';

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
