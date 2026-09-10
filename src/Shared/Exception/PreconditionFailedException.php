<?php

declare(strict_types=1);

namespace App\Shared\Exception;

/**
 * The client's If-Match did not describe the resource it is about to
 * overwrite — someone changed it in the meantime.
 *
 * Lives in Shared because the condition is an HTTP one, identical for every
 * resource; only the validator it is compared against is domain-specific.
 */
final class PreconditionFailedException extends \RuntimeException implements TranslatableException
{
    public const string KEY = 'exception.precondition_failed';

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
