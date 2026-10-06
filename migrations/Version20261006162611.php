<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261006162611 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE invitation (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, token_hash VARCHAR(64) NOT NULL, expires_at DATETIME NOT NULL, accepted_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, role VARCHAR(20) NOT NULL, project_id INT NOT NULL, invited_by_id INT NOT NULL, INDEX IDX_INVITATION_EMAIL (project_id, email), UNIQUE INDEX UNIQ_INVITATION_TOKEN (token_hash), INDEX IDX_F11D61A2166D1F9C (project_id), INDEX IDX_F11D61A2A7B4A7E3 (invited_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE invitation ADD CONSTRAINT FK_F11D61A2166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE invitation ADD CONSTRAINT FK_F11D61A2A7B4A7E3 FOREIGN KEY (invited_by_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE invitation DROP FOREIGN KEY FK_F11D61A2166D1F9C');
        $this->addSql('ALTER TABLE invitation DROP FOREIGN KEY FK_F11D61A2A7B4A7E3');
        $this->addSql('DROP TABLE invitation');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
