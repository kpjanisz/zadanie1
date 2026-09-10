<?php

declare(strict_types=1);

namespace App\Security\ReuseDetection;

use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Security\ReuseDetection\SpentRefreshToken;
use Gesdinet\JWTRefreshTokenBundle\Security\ReuseDetection\SpentRefreshTokenRegistryInterface;

/**
 * Workaround for gesdinet/jwt-refresh-token-bundle 3.0.0: reuse detection and
 * hash_tokens do not work together.
 *
 * The two halves of the registry are keyed by different things. Spending a token
 * records it under the value held on the entity, which with hash_tokens is the
 * hash ("sha256$…"); a replay is looked up under the value the client presented,
 * which is the token itself. The digests never meet, so recall() always answers
 * "never spent", the reuse event is never dispatched and the chain is never
 * revoked.
 *
 * Nothing about that is visible from outside: a replayed token is refused with
 * 401 either way, because its row is gone. What is missing is the consequence —
 * the descendants of the replayed token keep working, which is the entire point
 * of reuse detection. Proven by watching the pool: a rotation writes the key
 * for the hashed value while a replay reads the key for the raw one.
 *
 * This normalises the read side to the form the write side used. remember() is
 * left alone: it stores under the value that is actually on the entity, and the
 * raw token is not available there to store under instead.
 *
 * Remove once the bundle keys both sides the same way.
 */
final readonly class HashConsistentSpentRefreshTokenRegistry implements SpentRefreshTokenRegistryInterface
{
    /**
     * Duplicated from HashedRefreshTokenManager, where it is private. If the
     * bundle ever changes it, RefreshTokenReuseTest fails rather than the
     * revocation quietly going back to doing nothing.
     */
    private const string HASH_PREFIX = 'sha256$';

    public function __construct(
        private SpentRefreshTokenRegistryInterface $registry,
    ) {
    }

    #[\Override]
    public function remember(RefreshTokenInterface $refreshToken): void
    {
        $this->registry->remember($refreshToken);
    }

    #[\Override]
    public function recall(string $refreshToken): ?SpentRefreshToken
    {
        // The stored form first, since that is what every token spent under the
        // current configuration was recorded as. The raw value is still tried
        // afterwards, for tokens spent before hashing was turned on and for the
        // case where it is off altogether — the bundle keys those by the raw
        // value, and this decorator must not break the configuration it is not
        // there to fix.
        return $this->registry->recall(self::hash($refreshToken))
            ?? $this->registry->recall($refreshToken);
    }

    private static function hash(string $refreshToken): string
    {
        return self::HASH_PREFIX.hash('sha256', $refreshToken);
    }
}
