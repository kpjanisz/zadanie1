<?php

declare(strict_types=1);

namespace App\Shared\EventSubscriber;

use App\Shared\Exception\TranslatableException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Translates the `detail` of a problem document produced by a domain exception.
 *
 * Done in two steps because neither alone is enough: an exception's message
 * cannot be changed after construction, and replacing the exception object would
 * break the class-keyed exception_to_status mapping. So the key is captured when
 * the exception passes, and applied once API Platform has built the response.
 */
final readonly class TranslateExceptionDetailSubscriber
{
    private const string ATTRIBUTE = '_exception_translation';

    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * Runs before API Platform turns the exception into a response.
     */
    #[AsEventListener(event: KernelEvents::EXCEPTION, priority: 100)]
    public function capture(ExceptionEvent $event): void
    {
        $throwable = $event->getThrowable();

        if (!$throwable instanceof TranslatableException) {
            return;
        }

        $event->getRequest()->attributes->set(self::ATTRIBUTE, [
            'key' => $throwable->getTranslationKey(),
            'parameters' => $throwable->getTranslationParameters(),
        ]);
    }

    #[AsEventListener(event: KernelEvents::RESPONSE)]
    public function apply(ResponseEvent $event): void
    {
        /** @var array{key: string, parameters: array<string, string|int>}|null $translation */
        $translation = $event->getRequest()->attributes->get(self::ATTRIBUTE);

        if (null === $translation) {
            return;
        }

        $response = $event->getResponse();
        $content = $response->getContent();

        if (!\is_string($content) || '' === $content) {
            return;
        }

        try {
            /** @var array<string, mixed> $document */
            $document = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            // Not a JSON problem document (HTML error page in dev, for example).
            return;
        }

        if (!\array_key_exists('detail', $document)) {
            return;
        }

        $document['detail'] = $this->translator->trans(
            $translation['key'],
            $translation['parameters'],
        );

        $response->setContent(json_encode($document, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES));

        if ($response instanceof JsonResponse) {
            return;
        }

        $response->headers->remove('Content-Length');
    }
}
