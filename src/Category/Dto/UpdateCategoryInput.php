<?php

declare(strict_types=1);

namespace App\Category\Dto;

use App\Category\Entity\Category;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Partial update: null means "not supplied" and leaves the field untouched.
 * An explicitly blank value is still a validation error.
 */
final class UpdateCategoryInput
{
    #[Assert\NotBlank(allowNull: true, normalizer: 'trim')]
    #[Assert\Length(max: Category::CODE_MAX_LENGTH)]
    public ?string $code = null;
}
