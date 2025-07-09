<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250628225756 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE product_offer_brand (product_offer_id INT NOT NULL, brand_id INT NOT NULL, INDEX IDX_33501AAE98761E79 (product_offer_id), INDEX IDX_33501AAE44F5D008 (brand_id), PRIMARY KEY(product_offer_id, brand_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE product_offer_brand ADD CONSTRAINT FK_33501AAE98761E79 FOREIGN KEY (product_offer_id) REFERENCES product_offer (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE product_offer_brand ADD CONSTRAINT FK_33501AAE44F5D008 FOREIGN KEY (brand_id) REFERENCES brand (id) ON DELETE CASCADE
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE product_offer_brand DROP FOREIGN KEY FK_33501AAE98761E79
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE product_offer_brand DROP FOREIGN KEY FK_33501AAE44F5D008
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE product_offer_brand
        SQL);
    }
}
