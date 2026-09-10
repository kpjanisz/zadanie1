<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Category\Entity\Category;
use App\Security\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Shared setup for the API tests.
 *
 * Every test runs inside a transaction that dama/doctrine-test-bundle rolls back
 * afterwards, so each one may create the users and categories it needs without
 * cleaning up or depending on the order tests happen to run in.
 */
abstract class ApiWebTestCase extends ApiTestCase
{
    /**
     * Keeps the current behaviour explicitly; API Platform 5 flips the default
     * and deprecates relying on it.
     */
    protected static ?bool $alwaysBootKernel = true;

    protected const string ADMIN_EMAIL = 'admin@example.test';
    protected const string VIEWER_EMAIL = 'viewer@example.test';
    protected const string PASSWORD = 'TestPassw0rd!';

    protected function entityManager(): EntityManagerInterface
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }

    /**
     * @param list<string> $roles
     */
    protected function createUser(string $email, array $roles = []): User
    {
        $user = new User($email, '');

        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertInstanceOf(UserPasswordHasherInterface::class, $hasher);

        $user->setPassword($hasher->hashPassword($user, self::PASSWORD));
        $user->setRoles($roles);

        $this->entityManager()->persist($user);
        $this->entityManager()->flush();

        return $user;
    }

    protected function createCategory(string $code): Category
    {
        $category = new Category($code);

        $this->entityManager()->persist($category);
        $this->entityManager()->flush();

        return $category;
    }

    /**
     * Logs in over the real endpoint rather than minting a token directly, so the
     * firewall and the token contents are covered too.
     */
    protected function tokenFor(string $email): string
    {
        $response = static::createClient()->request('POST', '/api/auth/login', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => ['email' => $email, 'password' => self::PASSWORD],
        ]);

        self::assertResponseIsSuccessful();

        /** @var array{token: string} $payload */
        $payload = $response->toArray();

        return $payload['token'];
    }

    protected function clientFor(string $email): Client
    {
        $token = $this->tokenFor($email);

        return static::createClient([], ['headers' => ['Authorization' => 'Bearer '.$token]]);
    }
}
