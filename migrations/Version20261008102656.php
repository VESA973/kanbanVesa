<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Journal of the e-mails handed to the SMTP server (diagnosis of undelivered e-mails).
 */
final class Version20261008102656 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the e-mail delivery journal';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE email_log (id INT AUTO_INCREMENT NOT NULL, recipients VARCHAR(255) NOT NULL, subject VARCHAR(255) NOT NULL, status VARCHAR(20) NOT NULL, message_id VARCHAR(255) DEFAULT NULL, error LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_EMAIL_LOG_CREATED_AT (created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE email_log');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
