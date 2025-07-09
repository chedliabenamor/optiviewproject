<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250630090439 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_665648E95E237E06 ON color (name)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE review DROP FOREIGN KEY FK_794381C64584665A
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_794381C64584665A ON review
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE review CHANGE product_id product_variant_id INT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE review ADD CONSTRAINT FK_794381C6A80EF684 FOREIGN KEY (product_variant_id) REFERENCES product_variant (id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_794381C6A80EF684 ON review (product_variant_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            DROP INDEX UNIQ_665648E95E237E06 ON color
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE review DROP FOREIGN KEY FK_794381C6A80EF684
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_794381C6A80EF684 ON review
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE review CHANGE product_variant_id product_id INT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE review ADD CONSTRAINT FK_794381C64584665A FOREIGN KEY (product_id) REFERENCES product (id) ON UPDATE NO ACTION ON DELETE NO ACTION
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_794381C64584665A ON review (product_id)
        SQL);
    }
}
