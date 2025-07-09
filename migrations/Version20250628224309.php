<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250628224309 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE product_offer_product (product_offer_id INT NOT NULL, product_id INT NOT NULL, INDEX IDX_A8AFDE5698761E79 (product_offer_id), INDEX IDX_A8AFDE564584665A (product_id), PRIMARY KEY(product_offer_id, product_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE product_offer_product ADD CONSTRAINT FK_A8AFDE5698761E79 FOREIGN KEY (product_offer_id) REFERENCES product_offer (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE product_offer_product ADD CONSTRAINT FK_A8AFDE564584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE product_offer DROP FOREIGN KEY FK_888AFC624584665A
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_888AFC624584665A ON product_offer
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE product_offer DROP product_id
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE product_offer_product DROP FOREIGN KEY FK_A8AFDE5698761E79
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE product_offer_product DROP FOREIGN KEY FK_A8AFDE564584665A
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE product_offer_product
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE product_offer ADD product_id INT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE product_offer ADD CONSTRAINT FK_888AFC624584665A FOREIGN KEY (product_id) REFERENCES product (id) ON UPDATE NO ACTION ON DELETE NO ACTION
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_888AFC624584665A ON product_offer (product_id)
        SQL);
    }
}
