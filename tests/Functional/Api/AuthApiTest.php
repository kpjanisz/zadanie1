<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Security\Repository\UserRepository;
use Gesdinet\JWTRefreshTokenBundle\Model\RevokeRefreshTokenManagerInterface;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class AuthApiTest extends ApiWebTestCase
{
    public function testLoginReturnsAnAccessTokenAndARefreshToken(): void
    {
        $this->createUser(self::ADMIN_EMAIL, ['ROLE_ADMIN']);

        $payload = $this->login(self::ADMIN_EMAIL, self::PASSWORD);

        self::assertResponseIsSuccessful();
        self::assertArrayHasKey('token', $payload);
        self::assertArrayHasKey('refresh_token', $payload);
    }

    public function testLoginWithTheWrongPasswordIsRefused(): void
    {
        $this->createUser(self::ADMIN_EMAIL, ['ROLE_ADMIN']);

        $this->login(self::ADMIN_EMAIL, 'not-the-password');

        self::assertResponseStatusCodeSame(401);
    }

    public function testLoginWithAnUnknownAccountIsRefused(): void
    {
        $this->login('nobody@example.test', self::PASSWORD);

        self::assertResponseStatusCodeSame(401);
    }

    public function testATamperedTokenIsRefused(): void
    {
        $this->createUser(self::ADMIN_EMAIL, ['ROLE_ADMIN']);
        $token = $this->tokenFor(self::ADMIN_EMAIL);

        // Flip the last character of the signature.
        $tampered = substr($token, 0, -1).('a' === substr($token, -1) ? 'b' : 'a');

        static::createClient([], ['headers' => ['Authorization' => 'Bearer '.$tampered]])
            ->request('GET', '/api/products');

        self::assertResponseStatusCodeSame(401);
    }

    public function testAGarbageTokenIsRefused(): void
    {
        static::createClient([], ['headers' => ['Authorization' => 'Bearer not-a-jwt']])
            ->request('GET', '/api/products');

        self::assertResponseStatusCodeSame(401);
    }

    /**
     * single_use: a refresh token is spent the moment it is used.
     */
    public function testRefreshingRotatesTheTokenAndRetiresTheOldOne(): void
    {
        $this->createUser(self::ADMIN_EMAIL, ['ROLE_ADMIN']);
        $first = $this->login(self::ADMIN_EMAIL, self::PASSWORD);

        $second = $this->refresh($first['refresh_token']);
        self::assertResponseIsSuccessful();
        self::assertNotSame($first['refresh_token'], $second['refresh_token']);

        $this->refresh($first['refresh_token']);
        self::assertResponseStatusCodeSame(401, 'A rotated refresh token must not be usable again.');
    }

    /**
     * Rotation on its own leaves a stolen token working until the real client
     * happens to refresh — and once it does, the thief simply refreshes too and
     * the theft is never noticed. Recognising the replay is the other half: the
     * chain ends, so both copies stop working and the real user is signed out,
     * which is the part they can act on.
     *
     * Asserting the descendant matters more than asserting the replay. The
     * replay is refused whatever happens, because its row is gone — which is
     * precisely how a reuse detector that recognises nothing looks like one that
     * works.
     */
    public function testReplayingASpentRefreshTokenEndsTheWholeChain(): void
    {
        $email = \sprintf('replay+%s@example.test', bin2hex(random_bytes(6)));
        $this->createUser($email, ['ROLE_ADMIN']);

        $first = $this->login($email, self::PASSWORD);
        $second = $this->refresh($first['refresh_token']);
        $third = $this->refresh($second['refresh_token']);
        self::assertResponseIsSuccessful();

        // Somebody replays a token the client spent two rotations ago.
        $this->refresh($first['refresh_token']);
        self::assertResponseStatusCodeSame(401);

        $this->refresh($third['refresh_token']);
        self::assertResponseStatusCodeSame(
            401,
            'The live descendant of a replayed token must not survive: the session it belongs to is compromised.',
        );
    }

    public function testAnUnknownTokenDoesNotEndAnybodysSession(): void
    {
        // The other side of the same guard. Revoking a chain on any token the
        // storage does not recognise would let anyone sign anyone out by
        // guessing.
        $email = \sprintf('bystander+%s@example.test', bin2hex(random_bytes(6)));
        $this->createUser($email, ['ROLE_ADMIN']);

        $session = $this->login($email, self::PASSWORD);

        $this->refresh('a-token-that-was-never-issued');
        self::assertResponseStatusCodeSame(401);

        $this->refresh($session['refresh_token']);
        self::assertResponseIsSuccessful();
    }

    /**
     * A session is two credentials, so ending it has to take away both. Taking
     * away only the refresh token leaves the access token working for the rest
     * of its 900 s — on a shared computer that window is the whole reason the
     * button was pressed.
     *
     * The refusal of the refresh call is the weaker half of this test: it says
     * one call was refused, not that the session ended. The access token is
     * what says that.
     */
    public function testLoggingOutEndsTheSessionRatherThanOneCredential(): void
    {
        $email = \sprintf('logout+%s@example.test', bin2hex(random_bytes(6)));
        $this->createUser($email, ['ROLE_ADMIN']);
        $session = $this->login($email, self::PASSWORD);

        $this->apiCall($session['token']);
        self::assertResponseIsSuccessful();

        $this->logout($session['token'], $session['refresh_token']);
        self::assertResponseIsSuccessful();

        $this->refresh($session['refresh_token']);
        self::assertResponseStatusCodeSame(401, 'The refresh token must be gone.');

        $this->apiCall($session['token']);
        self::assertResponseStatusCodeSame(401, 'The access token must be gone too, without waiting out its TTL.');
    }

    /**
     * The blocklist is keyed per token rather than per user on purpose: the
     * revocation mark refuses every JWT issued before it, so reusing it here
     * would sign somebody out of their phone because they logged out on their
     * laptop.
     */
    public function testLoggingOutOfOneSessionLeavesTheOthersAlone(): void
    {
        $email = \sprintf('twosessions+%s@example.test', bin2hex(random_bytes(6)));
        $this->createUser($email, ['ROLE_ADMIN']);

        $laptop = $this->login($email, self::PASSWORD);
        $phone = $this->login($email, self::PASSWORD);

        $this->logout($laptop['token'], $laptop['refresh_token']);

        $this->apiCall($laptop['token']);
        self::assertResponseStatusCodeSame(401);

        $this->apiCall($phone['token']);
        self::assertResponseIsSuccessful('Logging out of one device must not sign the user out of the others.');
    }

    /**
     * States the limit of the mechanism rather than leaving it to be
     * rediscovered: a JWT is a bearer credential, and one the server is never
     * shown cannot be withdrawn — there is nothing to key the blocklist by.
     *
     * The refresh token still goes, so the session cannot be renewed; what
     * survives is the remainder of the access token's 900 s. Closing this would
     * mean revoking per user, which is the wrong trade (see the test above).
     * Clients must send their access token when they log out.
     */
    public function testAnAccessTokenTheServerIsNeverShownCannotBeWithdrawn(): void
    {
        $email = \sprintf('nobearer+%s@example.test', bin2hex(random_bytes(6)));
        $this->createUser($email, ['ROLE_ADMIN']);
        $session = $this->login($email, self::PASSWORD);

        $this->logout(null, $session['refresh_token']);
        self::assertResponseIsSuccessful();

        $this->refresh($session['refresh_token']);
        self::assertResponseStatusCodeSame(401);

        $this->apiCall($session['token']);
        self::assertResponseIsSuccessful('Documented limit: without the token in the request there is nothing to block.');
    }

    /**
     * Revoking a user's refresh tokens must also stop the access tokens already
     * issued to them — otherwise disabling a compromised account would leave it
     * usable for the rest of the JWT's lifetime.
     */
    public function testRevokingAUsersTokensAlsoRejectsTheJwtsAlreadyIssued(): void
    {
        // A unique identity per run: revocation marks live in a cache pool, which
        // no database transaction rolls back.
        $email = \sprintf('revoked+%s@example.test', bin2hex(random_bytes(6)));
        $user = $this->createUser($email, ['ROLE_ADMIN']);
        $token = $this->tokenFor($email);

        $client = static::createClient([], ['headers' => ['Authorization' => 'Bearer '.$token]]);
        $client->request('GET', '/api/products');
        self::assertResponseIsSuccessful();

        $manager = static::getContainer()->get('gesdinet_jwt_refresh_token.refresh_token_manager');
        self::assertInstanceOf(RevokeRefreshTokenManagerInterface::class, $manager);
        $manager->revokeAllForUser($user);

        $client->request('GET', '/api/products');
        self::assertResponseStatusCodeSame(401);
    }

    public function testADisabledAccountCannotAuthenticateAtAll(): void
    {
        $email = \sprintf('disabled+%s@example.test', bin2hex(random_bytes(6)));
        $this->createUser($email, ['ROLE_ADMIN']);
        $token = $this->tokenFor($email);

        // Re-read through the current container: issuing the token rebooted the
        // kernel, which leaves the instance created above detached, so mutating
        // it would flush nothing.
        $users = static::getContainer()->get(UserRepository::class);
        self::assertInstanceOf(UserRepository::class, $users);

        $user = $users->findOneByEmail($email);
        self::assertNotNull($user);

        $user->setIsActive(false);
        $this->entityManager()->flush();

        // The user checker runs on every request of a stateless firewall, so an
        // already-issued token stops working without waiting for its TTL.
        static::createClient([], ['headers' => ['Authorization' => 'Bearer '.$token]])
            ->request('GET', '/api/products');
        self::assertResponseStatusCodeSame(401);

        $this->login($email, self::PASSWORD);
        self::assertResponseStatusCodeSame(401);
    }

    /**
     * @return array<string, mixed>
     */
    private function login(string $email, string $password): array
    {
        $response = static::createClient()->request('POST', '/api/auth/login', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => ['email' => $email, 'password' => $password],
        ]);

        return 200 === $response->getStatusCode() ? $response->toArray() : [];
    }

    private function logout(?string $accessToken, string $refreshToken): void
    {
        $headers = ['Content-Type' => 'application/json'];

        if (null !== $accessToken) {
            $headers['Authorization'] = 'Bearer '.$accessToken;
        }

        static::createClient()->request('POST', '/api/auth/logout', [
            'headers' => $headers,
            'json' => ['refresh_token' => $refreshToken],
        ]);
    }

    /**
     * Any authenticated read: what is being asked is whether the token still
     * opens the API at all.
     */
    private function apiCall(string $accessToken): void
    {
        static::createClient([], ['headers' => ['Authorization' => 'Bearer '.$accessToken]])
            ->request('GET', '/api/products');
    }

    /**
     * @return array<string, mixed>
     */
    private function refresh(string $refreshToken): array
    {
        $response = static::createClient()->request('POST', '/api/auth/refresh', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => ['refresh_token' => $refreshToken],
        ]);

        return 200 === $response->getStatusCode() ? $response->toArray() : [];
    }
}
