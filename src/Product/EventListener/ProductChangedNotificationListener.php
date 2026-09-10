<?php

declare(strict_types=1);

namespace App\Product\EventListener;

use App\Notification\NotificationDispatcher;
use App\Product\Event\ProductChangedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * The seam between the product module and the notification module.
 */
#[AsEventListener]
final readonly class ProductChangedNotificationListener
{
    public function __construct(
        private NotificationDispatcher $dispatcher,
    ) {
    }

    public function __invoke(ProductChangedEvent $event): void
    {
        $this->dispatcher->dispatch($event->notification);
    }
}
