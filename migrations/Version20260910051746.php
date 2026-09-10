<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Optimistic locking on categories.
 */
final class Version20260910051746 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add category.version (optimistic locking)';
    }

    public function up(Schema $schema): void
    {
        // DEFAULT backfills existing rows AND stays: unlike an ordinary column,
        // a #[ORM\Version] int with a PHP default is expected by the mapping to
        // carry it, so dropping the default would leave schema:validate dirty.
        $this->addSql('ALTER TABLE category ADD version INT DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE category DROP version');
    }
}
