<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250907212200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE cart_item ADD product_variant_id INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE cart_item ADD CONSTRAINT FK_F0FE2527A80EF684 FOREIGN KEY (product_variant_id) REFERENCES product_variant (id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_F0FE2527A80EF684 ON cart_item (product_variant_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE cart_item DROP FOREIGN KEY FK_F0FE2527A80EF684
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_F0FE2527A80EF684 ON cart_item
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE cart_item DROP product_variant_id
        SQL);
    }
}
