<?php

declare(strict_types=1);

namespace App\Security\Command;

use App\Security\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RevokeRefreshTokenManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Enables or disables an account.
 *
 * Disabling is the "this account is compromised" button: besides flipping the
 * flag it revokes every refresh token the user holds, which — because
 * block_jwts_on_revocation is on — also makes the access tokens already issued
 * to them stop working, instead of staying valid for the rest of their TTL.
 */
#[AsCommand(
    name: 'app:user:set-active',
    description: 'Enables or disables a user account, revoking its tokens when disabling.',
)]
final class SetUserActiveCommand
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly RevokeRefreshTokenManagerInterface $refreshTokenManager,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'E-mail of the account')]
        string $email,
        #[Option(description: 'Enable the account instead of disabling it')]
        bool $enable = false,
    ): int {
        $user = $this->userRepository->findOneByEmail($email);

        if (null === $user) {
            $io->error(\sprintf('No user with the e-mail "%s".', $email));

            return Command::FAILURE;
        }

        $user->setIsActive($enable);
        $this->entityManager->flush();

        if ($enable) {
            $io->success(\sprintf('%s is enabled again.', $email));

            return Command::SUCCESS;
        }

        $revoked = $this->refreshTokenManager->revokeAllForUser($user);

        $io->success(\sprintf(
            '%s is disabled; %d refresh token(s) revoked and its access tokens are no longer accepted.',
            $email,
            $revoked,
        ));

        return Command::SUCCESS;
    }
}
