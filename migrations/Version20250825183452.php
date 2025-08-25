<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250825183452 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE wishlist_item (id INT AUTO_INCREMENT NOT NULL, wishlist_id INT NOT NULL, product_id INT NOT NULL, product_variant_id INT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_6424F4E8FB8E54CD (wishlist_id), INDEX IDX_6424F4E84584665A (product_id), INDEX IDX_6424F4E8A80EF684 (product_variant_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE wishlist_item ADD CONSTRAINT FK_6424F4E8FB8E54CD FOREIGN KEY (wishlist_id) REFERENCES wishlist (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE wishlist_item ADD CONSTRAINT FK_6424F4E84584665A FOREIGN KEY (product_id) REFERENCES product (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE wishlist_item ADD CONSTRAINT FK_6424F4E8A80EF684 FOREIGN KEY (product_variant_id) REFERENCES product_variant (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE wishlist_product DROP FOREIGN KEY FK_4C46D2D74584665A
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE wishlist_product DROP FOREIGN KEY FK_4C46D2D7FB8E54CD
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE wishlist_product
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE wishlist_product (wishlist_id INT NOT NULL, product_id INT NOT NULL, INDEX IDX_4C46D2D7FB8E54CD (wishlist_id), INDEX IDX_4C46D2D74584665A (product_id), PRIMARY KEY(wishlist_id, product_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = '' 
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE wishlist_product ADD CONSTRAINT FK_4C46D2D74584665A FOREIGN KEY (product_id) REFERENCES product (id) ON UPDATE NO ACTION ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE wishlist_product ADD CONSTRAINT FK_4C46D2D7FB8E54CD FOREIGN KEY (wishlist_id) REFERENCES wishlist (id) ON UPDATE NO ACTION ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE wishlist_item DROP FOREIGN KEY FK_6424F4E8FB8E54CD
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE wishlist_item DROP FOREIGN KEY FK_6424F4E84584665A
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE wishlist_item DROP FOREIGN KEY FK_6424F4E8A80EF684
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE wishlist_item
        SQL);
    }
}
