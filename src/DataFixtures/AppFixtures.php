<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Category\Entity\Category;
use App\Product\Entity\Product;
use App\Security\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Demo data so the API can be exercised immediately after `make up`.
 *
 * Development only — the passwords below are deliberately obvious and this
 * fixture must never be loaded anywhere real.
 */
final class AppFixtures extends Fixture
{
    public const string ADMIN_EMAIL = 'admin@example.test';
    public const string VIEWER_EMAIL = 'viewer@example.test';
    public const string PASSWORD = 'DevPassw0rd!';

    /** @var list<array{code: string}> */
    private const array CATEGORIES = [
        ['code' => 'AGD'],
        ['code' => 'RTV'],
        ['code' => 'KUCHNIA'],
        ['code' => 'OGROD'],
    ];

    /** @var list<array{name: string, price: string, categories: list<string>}> */
    private const array PRODUCTS = [
        ['name' => 'Pralka Bosch Serie 6', 'price' => '2499.00', 'categories' => ['AGD']],
        ['name' => 'Telewizor LG OLED 55"', 'price' => '4199.99', 'categories' => ['RTV']],
        ['name' => 'Ekspres do kawy DeLonghi', 'price' => '1899.50', 'categories' => ['AGD', 'KUCHNIA']],
        ['name' => 'Robot koszący Husqvarna', 'price' => '5999.00', 'categories' => ['OGROD']],
        ['name' => 'Blender kielichowy', 'price' => '349.90', 'categories' => ['KUCHNIA', 'AGD']],
    ];

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $this->createUser($manager, self::ADMIN_EMAIL, ['ROLE_ADMIN']);
        $this->createUser($manager, self::VIEWER_EMAIL, []);

        $categories = [];
        foreach (self::CATEGORIES as ['code' => $code]) {
            $category = new Category($code);
            $manager->persist($category);
            $categories[$code] = $category;
        }

        foreach (self::PRODUCTS as $definition) {
            $product = new Product($definition['name'], $definition['price']);

            foreach ($definition['categories'] as $code) {
                $product->addCategory(
                    $categories[$code] ?? throw new \LogicException(\sprintf('Fixture references unknown category "%s".', $code)),
                );
            }

            $manager->persist($product);
        }

        // One flush for the whole fixture set.
        $manager->flush();
    }

    /**
     * @param list<string> $roles
     */
    private function createUser(ObjectManager $manager, string $email, array $roles): void
    {
        $user = new User($email, '');
        $user->setPassword($this->passwordHasher->hashPassword($user, self::PASSWORD));
        $user->setRoles($roles);

        $manager->persist($user);
    }
}
