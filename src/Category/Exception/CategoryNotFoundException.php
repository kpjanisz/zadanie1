<?php

declare(strict_types=1);

namespace App\Category\Exception;

use App\Shared\Exception\TranslatableException;

/**
 * Domain exception. Carries no HTTP knowledge — the mapping to 404 lives in
 * config/packages/api_platform.yaml under exception_to_status.
 */
final class CategoryNotFoundException extends \RuntimeException implements TranslatableException
{
    public const string KEY = 'exception.category.not_found';

    /** @var array<string, string|int> */
    private array $parameters;

    public function __construct(int|string $id)
    {
        $this->parameters = ['%id%' => $id];

        // The message stays the key: it is what a log line shows, while the
        // client-facing text is produced from the catalogue at the edge.
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
