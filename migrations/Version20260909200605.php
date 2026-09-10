<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds the account activation flag, so a compromised account can be shut out
 * without deleting the row and its audit trail.
 */
final class Version20260909200605 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add app_user.is_active';
    }

    public function up(Schema $schema): void
    {
        // Added WITH a default so existing rows are backfilled as active — a
        // plain NOT NULL column would silently set every current account to 0
        // and lock everyone out. The default is then dropped to match the
        // mapping, which declares none, keeping doctrine:schema:validate clean.
        $this->addSql('ALTER TABLE app_user ADD is_active TINYINT(1) NOT NULL DEFAULT 1');
        $this->addSql('ALTER TABLE app_user ALTER COLUMN is_active DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user DROP is_active');
    }
}
