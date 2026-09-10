<?php

declare(strict_types=1);

namespace App\Shared\Exception;

/**
 * A domain exception whose message reaches the client.
 *
 * The `detail` of an RFC 7807 document is user-facing text, so it falls under
 * the same rule as every other string a user reads: it comes from a translation
 * catalogue, not from a literal in the code.
 *
 * The exception itself stays free of the translator — it is a value, thrown deep
 * in the domain where no service should be needed. It declares WHAT to say; the
 * translation happens once, at the edge, in TranslateExceptionDetailSubscriber.
 */
interface TranslatableException extends \Throwable
{
    public function getTranslationKey(): string;

    /**
     * @return array<string, string|int>
     */
    public function getTranslationParameters(): array;
}
