<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261007114921 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE smtp_settings (id INT NOT NULL, host VARCHAR(255) NOT NULL, port INT NOT NULL, encryption VARCHAR(10) NOT NULL, username VARCHAR(255) DEFAULT NULL, encrypted_password LONGTEXT DEFAULT NULL, from_address VARCHAR(180) NOT NULL, from_name VARCHAR(100) DEFAULT NULL, updated_at DATETIME DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE task DROP FOREIGN KEY `FK_527EDB25B03A8386`');
        $this->addSql('ALTER TABLE task CHANGE created_by_id created_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB25B03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE `user` ADD is_active TINYINT DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE smtp_settings');
        $this->addSql('ALTER TABLE task DROP FOREIGN KEY FK_527EDB25B03A8386');
        $this->addSql('ALTER TABLE task CHANGE created_by_id created_by_id INT NOT NULL');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT `FK_527EDB25B03A8386` FOREIGN KEY (created_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE `user` DROP is_active');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
