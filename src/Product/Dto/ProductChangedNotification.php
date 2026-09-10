<?php

declare(strict_types=1);

namespace App\Product\Dto;

use App\Notification\Contract\NotificationInterface;
use App\Notification\Enum\OperationAction;
use App\Product\Entity\Product;

/**
 * "A product and its categories were saved.".
 *
 * Built from the entity once, at dispatch time, so channels never touch Doctrine
 * and can be unit-tested with a plain value object.
 */
final readonly class ProductChangedNotification implements NotificationInterface
{
    public const string TYPE = 'product.changed';

    /**
     * @param list<array{id: int, code: string}> $categories
     */
    public function __construct(
        private int $productId,
        private string $productName,
        private string $productPrice,
        private array $categories,
        private OperationAction $action,
    ) {
    }

    public static function fromProduct(Product $product, OperationAction $action): self
    {
        $categories = [];
        foreach ($product->getCategories() as $category) {
            $categories[] = [
                'id' => $category->getId() ?? 0,
                'code' => $category->getCode(),
            ];
        }

        return new self(
            productId: $product->getId() ?? throw new \LogicException('Cannot notify about an unpersisted product.'),
            productName: $product->getName(),
            productPrice: $product->getPrice(),
            categories: $categories,
            action: $action,
        );
    }

    public function getType(): string
    {
        return self::TYPE;
    }

    public function getAction(): OperationAction
    {
        return $this->action;
    }

    public function getSubjectType(): string
    {
        return 'Product';
    }

    public function getSubjectId(): int
    {
        return $this->productId;
    }

    public function getSubjectKey(): string
    {
        return \sprintf('notification.%s.subject', self::TYPE);
    }

    /**
     * @return array{productId: int, name: string, price: string, action: string, categories: list<array{id: int, code: string}>}
     */
    public function getContext(): array
    {
        return [
            'productId' => $this->productId,
            'name' => $this->productName,
            'price' => $this->productPrice,
            'action' => $this->action->value,
            'categories' => $this->categories,
        ];
    }
}
