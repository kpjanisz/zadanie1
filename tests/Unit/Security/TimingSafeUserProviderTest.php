<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Security\Entity\User;
use App\Security\TimingSafeUserProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * The elapsed time itself cannot be asserted reliably, so the test pins the
 * mechanism instead: the hasher must run on the miss path and only there.
 */
#[CoversClass(TimingSafeUserProvider::class)]
final class TimingSafeUserProviderTest extends TestCase
{
    public function testHashesAThrowawayValueWhenTheAccountDoesNotExist(): void
    {
        $inner = $this->createStub(UserProviderInterface::class);
        $inner->method('loadUserByIdentifier')->willThrowException(new UserNotFoundException());

        $hasher = $this->createMock(PasswordHasherInterface::class);
        $hasher->expects(self::once())->method('verify');

        $this->expectException(UserNotFoundException::class);

        new TimingSafeUserProvider($inner, $this->factoryReturning($hasher))
            ->loadUserByIdentifier('ghost@example.test');
    }

    public function testDoesNoExtraWorkWhenTheAccountExists(): void
    {
        $user = new User('admin@example.test', 'hash');

        $inner = $this->createStub(UserProviderInterface::class);
        $inner->method('loadUserByIdentifier')->willReturn($user);

        $hasher = $this->createMock(PasswordHasherInterface::class);
        $hasher->expects(self::never())->method('verify');

        $found = new TimingSafeUserProvider($inner, $this->factoryReturning($hasher))
            ->loadUserByIdentifier('admin@example.test');

        self::assertSame($user, $found);
    }

    private function factoryReturning(PasswordHasherInterface $hasher): PasswordHasherFactoryInterface
    {
        $factory = $this->createStub(PasswordHasherFactoryInterface::class);
        $factory->method('getPasswordHasher')->willReturn($hasher);

        return $factory;
    }
}
