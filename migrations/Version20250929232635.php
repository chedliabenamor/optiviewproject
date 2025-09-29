<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250929232635 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE brand DROP is_archived
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE category DROP is_archived
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE `order` ADD applied_points INT DEFAULT 0 NOT NULL, ADD points_discount NUMERIC(10, 2) DEFAULT '0' NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE product DROP is_archived
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE brand ADD is_archived TINYINT(1) DEFAULT 0 NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE category ADD is_archived TINYINT(1) DEFAULT 0 NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE `order` DROP applied_points, DROP points_discount
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE product ADD is_archived TINYINT(1) DEFAULT 0 NOT NULL
        SQL);
    }
}
