<?php

declare(strict_types=1);

namespace App\Product\Entity;

use App\Category\Entity\Category;
use App\Product\Repository\ProductRepository;
use App\Shared\Entity\TimestampableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\Table(name: 'product')]
#[ORM\Index(name: 'idx_product_name', columns: ['name'])]
#[ORM\Index(name: 'idx_product_created_at', columns: ['created_at'])]
#[ORM\HasLifecycleCallbacks]
class Product
{
    use TimestampableTrait;

    public const int NAME_MAX_LENGTH = 255;

    /** Fits DECIMAL(12,2): ten integer digits plus two decimals. */
    public const int PRICE_PRECISION = 12;
    public const int PRICE_SCALE = 2;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Guards against lost updates: two PATCHes that read the same row and write
     * back concurrently would otherwise have the second silently overwrite the
     * first. Doctrine appends `WHERE version = ?` to every UPDATE and raises
     * OptimisticLockException when no row matches — mapped to 409.
     */
    #[ORM\Version]
    #[ORM\Column(type: Types::INTEGER)]
    private int $version = 1;

    #[ORM\Column(length: self::NAME_MAX_LENGTH)]
    private string $name;

    /**
     * Money is DECIMAL in the database and a string in PHP. Doctrine's `decimal`
     * type deliberately hydrates to string: casting to float would silently lose
     * cents on values a binary float cannot represent.
     */
    #[ORM\Column(type: Types::DECIMAL, precision: self::PRICE_PRECISION, scale: self::PRICE_SCALE)]
    private string $price;

    /**
     * Owning side, unidirectional. A product must belong to at least one category;
     * MySQL cannot express that on a join table, so it is enforced by the input
     * DTO's Assert\Count and re-checked in the processor before flush.
     *
     * @var Collection<int, Category>
     */
    #[ORM\ManyToMany(targetEntity: Category::class)]
    #[ORM\JoinTable(name: 'product_category')]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'category_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $categories;

    public function __construct(string $name, string $price)
    {
        $this->name = $name;
        $this->price = $price;
        $this->categories = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getPrice(): string
    {
        return $this->price;
    }

    public function setPrice(string $price): void
    {
        $this->price = $price;
    }

    /**
     * @return Collection<int, Category>
     */
    public function getCategories(): Collection
    {
        return $this->categories;
    }

    public function addCategory(Category $category): void
    {
        if (!$this->categories->contains($category)) {
            $this->categories->add($category);
        }
    }

    public function removeCategory(Category $category): void
    {
        $this->categories->removeElement($category);
    }

    public function clearCategories(): void
    {
        $this->categories->clear();
    }
}
