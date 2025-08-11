<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250811120434 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE post ADD author_id INT NOT NULL, ADD image VARCHAR(255) DEFAULT NULL, ADD date DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE post ADD CONSTRAINT FK_5A8A6C8DF675F31B FOREIGN KEY (author_id) REFERENCES `user` (id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_5A8A6C8DF675F31B ON post (author_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE post DROP FOREIGN KEY FK_5A8A6C8DF675F31B
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_5A8A6C8DF675F31B ON post
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE post DROP author_id, DROP image, DROP date
        SQL);
    }
}
