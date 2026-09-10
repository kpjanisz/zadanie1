<?php

declare(strict_types=1);

namespace App\Security\Command;

use App\Security\Entity\User;
use App\Security\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Bootstraps an account so the API can be used at all. There is no public
 * registration endpoint: this project's task is products and categories, not
 * user self-service.
 */
#[AsCommand(
    name: 'app:user:create',
    description: 'Creates a user account (optionally an administrator).',
)]
final class CreateUserCommand
{
    private const int MIN_PASSWORD_LENGTH = 12;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'E-mail address, used as the login identifier')]
        string $email,
        #[Argument(description: 'Plain password; it is hashed before storage and never logged')]
        #[\SensitiveParameter]
        string $password,
        #[Option(description: 'Grant ROLE_ADMIN, which is required for every write operation')]
        bool $admin = false,
    ): int {
        if (false === filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            $io->error(\sprintf('"%s" is not a valid e-mail address.', $email));

            return Command::INVALID;
        }

        if (\strlen($password) < self::MIN_PASSWORD_LENGTH) {
            $io->error(\sprintf('The password must be at least %d characters long.', self::MIN_PASSWORD_LENGTH));

            return Command::INVALID;
        }

        if (null !== $this->userRepository->findOneByEmail($email)) {
            $io->error(\sprintf('A user with the e-mail "%s" already exists.', $email));

            return Command::FAILURE;
        }

        // Hash first, then construct: the entity never holds the plain password.
        $user = new User($email, '');
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->setRoles($admin ? ['ROLE_ADMIN'] : []);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(\sprintf('Created %s with roles: %s', $email, implode(', ', $user->getRoles())));

        return Command::SUCCESS;
    }
}
