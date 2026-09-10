<?php

declare(strict_types=1);

namespace App\Category\Contract;

/**
 * Answers "is anything still using this category?".
 *
 * The Category module needs the answer before allowing a delete, but must not
 * reach into Product to get it — that would make the two modules depend on each
 * other. Category declares the question; whichever module owns the relationship
 * answers it.
 */
interface CategoryUsageCheckerInterface
{
    public function countUsages(int $categoryId): int;
}
