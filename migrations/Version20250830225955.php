<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250830225955 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE review ADD product_id INT NOT NULL, CHANGE product_variant_id product_variant_id INT DEFAULT NULL, CHANGE rating rating SMALLINT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE review ADD CONSTRAINT FK_794381C64584665A FOREIGN KEY (product_id) REFERENCES product (id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_794381C64584665A ON review (product_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE review DROP FOREIGN KEY FK_794381C64584665A
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_794381C64584665A ON review
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE review DROP product_id, CHANGE product_variant_id product_variant_id INT NOT NULL, CHANGE rating rating SMALLINT NOT NULL
        SQL);
    }
}
