<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250628213103 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE product_offer ADD product_variant_id INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE product_offer ADD CONSTRAINT FK_888AFC62A80EF684 FOREIGN KEY (product_variant_id) REFERENCES product_variant (id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_888AFC62A80EF684 ON product_offer (product_variant_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE product_offer DROP FOREIGN KEY FK_888AFC62A80EF684
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_888AFC62A80EF684 ON product_offer
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE product_offer DROP product_variant_id
        SQL);
    }
}
