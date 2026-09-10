<?php

declare(strict_types=1);

namespace App\Security\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken as BaseRefreshToken;

/**
 * Concrete refresh token.
 *
 * The bundle ships its RefreshToken as a Doctrine *mapped superclass* (see
 * vendor/gesdinet/jwt-refresh-token-bundle/config/doctrine/RefreshToken.orm.xml),
 * so the application has to own the entity. Columns — refresh_token (unique),
 * username, valid, family, family_valid — are inherited from that mapping; only
 * the table and the lookup indexes are declared here.
 *
 * `family` groups the tokens issued from one login, which is what makes reuse
 * detection possible: replaying a rotated token invalidates the whole family.
 */
#[ORM\Entity]
#[ORM\Table(name: 'refresh_token')]
#[ORM\Index(name: 'idx_refresh_token_username', columns: ['username'])]
#[ORM\Index(name: 'idx_refresh_token_family', columns: ['family'])]
#[ORM\Index(name: 'idx_refresh_token_valid', columns: ['valid'])]
class RefreshToken extends BaseRefreshToken
{
}
