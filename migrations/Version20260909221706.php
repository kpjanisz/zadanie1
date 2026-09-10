<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Optimistic locking on products, and removal of a column that could never change.
 */
final class Version20260909221706 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add product.version (optimistic locking); drop operation_log.updated_at';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE operation_log DROP updated_at');
        $this->addSql('ALTER TABLE product ADD version INT DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE operation_log ADD updated_at DATETIME NOT NULL COMMENT \'UTC\'');
        $this->addSql('ALTER TABLE product DROP version');
    }
}
