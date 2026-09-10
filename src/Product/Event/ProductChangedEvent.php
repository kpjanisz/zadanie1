<?php

declare(strict_types=1);

namespace App\Product\Event;

use App\Product\Dto\ProductChangedNotification;

/**
 * Announced by the product processor AFTER the transaction has committed —
 * never before, because a notification about a product that failed to persist
 * would be a lie.
 *
 * It carries the finished value object rather than the entity: that keeps the
 * payload serialisable, so swapping this event for a Messenger message is the
 * whole change needed to make delivery asynchronous.
 */
final readonly class ProductChangedEvent
{
    public function __construct(
        public ProductChangedNotification $notification,
    ) {
    }
}
