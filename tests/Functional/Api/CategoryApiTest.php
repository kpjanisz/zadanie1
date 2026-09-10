<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Category\Entity\Category;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class CategoryApiTest extends ApiWebTestCase
{
    public function testCreatingACategory(): void
    {
        $this->createUser(self::ADMIN_EMAIL, ['ROLE_ADMIN']);

        $this->clientFor(self::ADMIN_EMAIL)->request('POST', '/api/categories', [
            'json' => ['code' => 'AGD'],
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonContains(['code' => 'AGD']);
    }

    public function testTheCodeIsUnique(): void
    {
        $this->createUser(self::ADMIN_EMAIL, ['ROLE_ADMIN']);
        $this->createCategory('AGD');

        $this->clientFor(self::ADMIN_EMAIL)->request('POST', '/api/categories', [
            'json' => ['code' => 'AGD'],
        ]);

        self::assertResponseStatusCodeSame(409);
    }

    public function testTheCodeIsCappedAtTenCharacters(): void
    {
        $this->createUser(self::ADMIN_EMAIL, ['ROLE_ADMIN']);

        $this->clientFor(self::ADMIN_EMAIL)->request('POST', '/api/categories', [
            'json' => ['code' => str_repeat('X', Category::CODE_MAX_LENGTH + 1)],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains(['violations' => [['propertyPath' => 'code']]]);
    }

    public function testTenCharactersIsStillAccepted(): void
    {
        $this->createUser(self::ADMIN_EMAIL, ['ROLE_ADMIN']);

        $this->clientFor(self::ADMIN_EMAIL)->request('POST', '/api/categories', [
            'json' => ['code' => str_repeat('X', Category::CODE_MAX_LENGTH)],
        ]);

        self::assertResponseStatusCodeSame(201);
    }

    public function testRenamingACategoryOntoAnExistingCodeConflicts(): void
    {
        $this->createUser(self::ADMIN_EMAIL, ['ROLE_ADMIN']);
        $this->createCategory('AGD');
        $rtv = $this->createCategory('RTV');

        $this->clientFor(self::ADMIN_EMAIL)->request('PATCH', '/api/categories/'.$rtv->getId(), [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['code' => 'AGD'],
        ]);

        self::assertResponseStatusCodeSame(409);
    }

    public function testACategoryMayKeepItsOwnCodeOnUpdate(): void
    {
        $this->createUser(self::ADMIN_EMAIL, ['ROLE_ADMIN']);
        $agd = $this->createCategory('AGD');

        // The uniqueness check must exclude the row being updated, or a no-op
        // PATCH would conflict with itself.
        $this->clientFor(self::ADMIN_EMAIL)->request('PATCH', '/api/categories/'.$agd->getId(), [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['code' => 'AGD'],
        ]);

        self::assertResponseIsSuccessful();
    }

    /**
     * Products must belong to at least one category, so a category still in use
     * cannot disappear from under them.
     */
    public function testACategoryInUseCannotBeDeleted(): void
    {
        $this->createUser(self::ADMIN_EMAIL, ['ROLE_ADMIN']);
        $category = $this->createCategory('AGD');

        $client = $this->clientFor(self::ADMIN_EMAIL);
        $client->request('POST', '/api/products', [
            'json' => ['name' => 'Pralka', 'price' => '1.00', 'categoryIds' => [$category->getId()]],
        ]);
        self::assertResponseStatusCodeSame(201);

        $client->request('DELETE', '/api/categories/'.$category->getId());
        self::assertResponseStatusCodeSame(409);
    }

    public function testAnUnusedCategoryCanBeDeleted(): void
    {
        $this->createUser(self::ADMIN_EMAIL, ['ROLE_ADMIN']);
        $category = $this->createCategory('AGD');

        $this->clientFor(self::ADMIN_EMAIL)->request('DELETE', '/api/categories/'.$category->getId());

        self::assertResponseStatusCodeSame(204);
    }

    public function testUnknownCategoryReturnsNotFound(): void
    {
        $this->createUser(self::ADMIN_EMAIL, ['ROLE_ADMIN']);

        $this->clientFor(self::ADMIN_EMAIL)->request('GET', '/api/categories/999999');

        self::assertResponseStatusCodeSame(404);
    }
}
