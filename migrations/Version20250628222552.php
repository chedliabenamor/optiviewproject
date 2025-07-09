<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250628222552 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE product_offer_product_variant (product_offer_id INT NOT NULL, product_variant_id INT NOT NULL, INDEX IDX_45A3ABE598761E79 (product_offer_id), INDEX IDX_45A3ABE5A80EF684 (product_variant_id), PRIMARY KEY(product_offer_id, product_variant_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE product_offer_product_variant ADD CONSTRAINT FK_45A3ABE598761E79 FOREIGN KEY (product_offer_id) REFERENCES product_offer (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE product_offer_product_variant ADD CONSTRAINT FK_45A3ABE5A80EF684 FOREIGN KEY (product_variant_id) REFERENCES product_variant (id) ON DELETE CASCADE
        SQL);
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

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE product_offer_product_variant DROP FOREIGN KEY FK_45A3ABE598761E79
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE product_offer_product_variant DROP FOREIGN KEY FK_45A3ABE5A80EF684
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE product_offer_product_variant
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE product_offer ADD product_variant_id INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE product_offer ADD CONSTRAINT FK_888AFC62A80EF684 FOREIGN KEY (product_variant_id) REFERENCES product_variant (id) ON UPDATE NO ACTION ON DELETE NO ACTION
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_888AFC62A80EF684 ON product_offer (product_variant_id)
        SQL);
    }
}
