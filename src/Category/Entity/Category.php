<?php

declare(strict_types=1);

namespace App\Category\Entity;

use App\Category\Repository\CategoryRepository;
use App\Shared\Entity\TimestampableTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CategoryRepository::class)]
#[ORM\Table(name: 'category')]
#[ORM\UniqueConstraint(name: 'uniq_category_code', columns: ['code'])]
#[ORM\HasLifecycleCallbacks]
class Category
{
    use TimestampableTrait;

    /**
     * Enforced by the database (uniq_category_code), by the validator on the input
     * DTO, and by an explicit pre-flush check in the processor that turns the race
     * into a 409 instead of a driver exception.
     */
    public const int CODE_MAX_LENGTH = 10;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Same guard as on Product: the code is unique and appears in every
     * product's representation, so silently overwriting somebody's rename is
     * not a harmless outcome.
     */
    #[ORM\Version]
    #[ORM\Column(type: Types::INTEGER)]
    private int $version = 1;

    #[ORM\Column(length: self::CODE_MAX_LENGTH)]
    private string $code;

    public function __construct(string $code)
    {
        $this->code = $code;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
    }
}
