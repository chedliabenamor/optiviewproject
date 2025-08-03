<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250803122701 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE offer (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, discount NUMERIC(10, 2) NOT NULL, active TINYINT(1) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE offer_products (offer_id INT NOT NULL, product_id INT NOT NULL, INDEX IDX_CAC7EA3853C674EE (offer_id), INDEX IDX_CAC7EA384584665A (product_id), PRIMARY KEY(offer_id, product_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE offer_products ADD CONSTRAINT FK_CAC7EA3853C674EE FOREIGN KEY (offer_id) REFERENCES offer (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE offer_products ADD CONSTRAINT FK_CAC7EA384584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE `order` ADD shipping_provider VARCHAR(50) DEFAULT NULL, ADD delivery_type VARCHAR(50) DEFAULT NULL, ADD destination VARCHAR(50) DEFAULT NULL, DROP discount_amount, DROP currency, CHANGE payment_method payment_method VARCHAR(50) DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE offer_products DROP FOREIGN KEY FK_CAC7EA3853C674EE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE offer_products DROP FOREIGN KEY FK_CAC7EA384584665A
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE offer
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE offer_products
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE `order` ADD discount_amount NUMERIC(10, 2) DEFAULT NULL, ADD currency VARCHAR(10) DEFAULT NULL, DROP shipping_provider, DROP delivery_type, DROP destination, CHANGE payment_method payment_method VARCHAR(255) DEFAULT NULL
        SQL);
    }
}
