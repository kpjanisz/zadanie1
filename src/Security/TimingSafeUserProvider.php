<?php

declare(strict_types=1);

namespace App\Security;

use App\Security\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Closes a user-enumeration side channel.
 *
 * Symfony verifies a password only once a user has been found, so a login for an
 * unknown address returns without ever running the hasher. Measured on this
 * project before the fix: ~350 ms for an existing account with a wrong password
 * against ~18 ms for one that does not exist — a 20x difference, and a reliable
 * oracle for harvesting valid e-mail addresses despite both responses being 401.
 *
 * Hashing a throwaway value on the miss path costs the same as the real check
 * and removes the signal. It does make a failed lookup deliberately expensive,
 * which is why login_throttling sits in front of this firewall.
 *
 * @implements UserProviderInterface<User>
 */
final readonly class TimingSafeUserProvider implements UserProviderInterface, PasswordUpgraderInterface
{
    /**
     * A pre-computed bcrypt digest of no significance. Verifying against it
     * exercises the same work as verifying a real one.
     */
    private const string DUMMY_HASH = '$2y$13$Wl0zdMDL5cDxJZ0FGbNQ7.zKKTMdd0oXqJEd9nEo0y4b2uQ0qzZ6O';

    /**
     * @param UserProviderInterface<User> $inner
     */
    public function __construct(
        private UserProviderInterface $inner,
        private PasswordHasherFactoryInterface $hasherFactory,
    ) {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        try {
            return $this->inner->loadUserByIdentifier($identifier);
        } catch (UserNotFoundException $e) {
            $this->burnEquivalentTime();

            throw $e;
        }
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        return $this->inner->refreshUser($user);
    }

    public function supportsClass(string $class): bool
    {
        return $this->inner->supportsClass($class);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$this->inner instanceof PasswordUpgraderInterface) {
            return;
        }

        if (!$user instanceof User) {
            throw new UnsupportedUserException(\sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $this->inner->upgradePassword($user, $newHashedPassword);
    }

    /**
     * Runs the same hash verification the found-user path would have run. The
     * result is discarded: the point is the elapsed time, not the answer.
     */
    private function burnEquivalentTime(): void
    {
        $this->hasherFactory
            ->getPasswordHasher(User::class)
            ->verify(self::DUMMY_HASH, 'a password that is never correct');
    }
}
