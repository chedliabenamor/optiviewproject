<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250728211909 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE `order` ADD subtotal NUMERIC(10, 2) DEFAULT NULL, ADD tax_amount NUMERIC(10, 2) DEFAULT NULL, ADD shipping_fee NUMERIC(10, 2) DEFAULT NULL, ADD discount_amount NUMERIC(10, 2) DEFAULT NULL, ADD currency VARCHAR(10) DEFAULT NULL, ADD shipping_method VARCHAR(100) DEFAULT NULL, ADD notes LONGTEXT DEFAULT NULL, ADD tracking_number VARCHAR(100) DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE `order` DROP subtotal, DROP tax_amount, DROP shipping_fee, DROP discount_amount, DROP currency, DROP shipping_method, DROP notes, DROP tracking_number
        SQL);
    }
}
