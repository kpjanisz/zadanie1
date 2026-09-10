<?php

declare(strict_types=1);

namespace App\Category\Dto;

use App\Category\Entity\Category;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Whitelist of what a client may set when creating a category.
 *
 * Uniqueness is not expressed here: a validator constraint would still race with
 * a concurrent insert, so the processor checks it and the database has the final
 * say via uniq_category_code.
 */
final class CreateCategoryInput
{
    #[Assert\NotBlank(normalizer: 'trim')]
    #[Assert\Length(max: Category::CODE_MAX_LENGTH)]
    public ?string $code = null;
}
