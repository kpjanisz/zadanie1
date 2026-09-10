<?php

declare(strict_types=1);

namespace App\Security;

use App\Security\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Refuses deactivated accounts.
 *
 * Runs on authentication, which on a stateless firewall means every request —
 * so a JWT issued before the account was disabled stops working immediately,
 * without waiting for its TTL.
 */
final class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (!$user->isActive()) {
            // Deliberately vague: the caller learns the credentials will not
            // work, not why, so this cannot be used to probe account states.
            throw new CustomUserMessageAccountStatusException('security.account_disabled');
        }
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
    }
}
