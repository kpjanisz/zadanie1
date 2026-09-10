<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Security\Entity\RefreshToken;
use App\Security\ReuseDetection\HashConsistentSpentRefreshTokenRegistry;
use Gesdinet\JWTRefreshTokenBundle\Model\HashedRefreshTokenManager;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Security\ReuseDetection\CacheSpentRefreshTokenRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * Pins the two halves of the registry to the same key.
 *
 * The whole test runs against the bundle's own classes — its hashing manager
 * writes the stored value, its cache registry does the remembering — so it
 * fails if the bundle ever changes the form, instead of agreeing with a
 * constant copied into our decorator and quietly revoking nothing.
 */
#[CoversClass(HashConsistentSpentRefreshTokenRegistry::class)]
final class HashConsistentSpentRefreshTokenRegistryTest extends TestCase
{
    private const string PRESENTED = 'the-value-a-client-holds';

    public function testAReplayIsRecognisedThoughTheTokenIsStoredHashed(): void
    {
        $pool = new ArrayAdapter();
        $registry = new CacheSpentRefreshTokenRegistry($pool, 3600);

        $registry->remember($this->spentToken());

        $recalled = new HashConsistentSpentRefreshTokenRegistry($registry)->recall(self::PRESENTED);

        self::assertNotNull($recalled, 'A token spent under hash_tokens must be recognised on replay.');
        self::assertSame('the-family', $recalled->family, 'Without the family there is no chain to revoke.');
        self::assertSame('user@example.test', $recalled->username);
    }

    public function testTheUndecoratedRegistryIsTheDefectThisGuards(): void
    {
        // Not a test of the bundle: it states what breaks if the decorator is
        // unwired, which is the failure mode that hides — a replay is refused
        // with 401 either way, and only the chain silently survives.
        $pool = new ArrayAdapter();
        $registry = new CacheSpentRefreshTokenRegistry($pool, 3600);

        $registry->remember($this->spentToken());

        self::assertNull($registry->recall(self::PRESENTED));
    }

    public function testATokenSpentInTheClearIsStillRecognised(): void
    {
        // hash_tokens off, or a token stored before it was turned on: the bundle
        // keys those by the raw value, and the decorator must not lose them.
        $pool = new ArrayAdapter();
        $registry = new CacheSpentRefreshTokenRegistry($pool, 3600);

        $token = new RefreshToken();
        $token->setRefreshToken(self::PRESENTED);
        $token->setUsername('user@example.test');
        $token->setFamily('the-family');

        $registry->remember($token);

        self::assertNotNull(
            new HashConsistentSpentRefreshTokenRegistry($registry)->recall(self::PRESENTED),
        );
    }

    public function testATokenThatWasNeverSpentIsNotReportedAsAReplay(): void
    {
        // Over-revocation would be worse than the defect: any wrong token would
        // sign somebody out.
        $registry = new CacheSpentRefreshTokenRegistry(new ArrayAdapter(), 3600);

        self::assertNull(
            new HashConsistentSpentRefreshTokenRegistry($registry)->recall(self::PRESENTED),
        );
    }

    /**
     * A token in the state the listener records it in: hashed by the bundle's
     * own manager, exactly as it was written to storage.
     */
    private function spentToken(): RefreshToken
    {
        $token = new RefreshToken();
        $token->setRefreshToken(self::PRESENTED);
        $token->setUsername('user@example.test');
        $token->setFamily('the-family');

        new HashedRefreshTokenManager($this->createStub(RefreshTokenManagerInterface::class))->save($token);

        self::assertNotSame(self::PRESENTED, $token->getRefreshToken(), 'The manager must have hashed it.');

        return $token;
    }
}
