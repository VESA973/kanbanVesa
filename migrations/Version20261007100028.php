<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261007100028 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE checklist_item (id INT AUTO_INCREMENT NOT NULL, is_done TINYINT NOT NULL, label VARCHAR(255) NOT NULL, position INT NOT NULL, task_id INT NOT NULL, INDEX IDX_CHECKLIST_ITEM_POSITION (task_id, position), INDEX IDX_99EB20F98DB60186 (task_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE comment (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, content LONGTEXT NOT NULL, task_id INT NOT NULL, author_id INT NOT NULL, INDEX IDX_COMMENT_TASK_DATE (task_id, created_at), INDEX IDX_9474526C8DB60186 (task_id), INDEX IDX_9474526CF675F31B (author_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE label (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(30) NOT NULL, color VARCHAR(20) NOT NULL, project_id INT NOT NULL, UNIQUE INDEX UNIQ_LABEL_PROJECT_NAME (project_id, name), INDEX IDX_EA750E8166D1F9C (project_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE task_label (task_id INT NOT NULL, label_id INT NOT NULL, INDEX IDX_C9034BC88DB60186 (task_id), INDEX IDX_C9034BC833B92F39 (label_id), PRIMARY KEY (task_id, label_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE checklist_item ADD CONSTRAINT FK_99EB20F98DB60186 FOREIGN KEY (task_id) REFERENCES task (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526C8DB60186 FOREIGN KEY (task_id) REFERENCES task (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526CF675F31B FOREIGN KEY (author_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE label ADD CONSTRAINT FK_EA750E8166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE task_label ADD CONSTRAINT FK_C9034BC88DB60186 FOREIGN KEY (task_id) REFERENCES task (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE task_label ADD CONSTRAINT FK_C9034BC833B92F39 FOREIGN KEY (label_id) REFERENCES label (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE task ADD due_reminder_sent_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE checklist_item DROP FOREIGN KEY FK_99EB20F98DB60186');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526C8DB60186');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526CF675F31B');
        $this->addSql('ALTER TABLE label DROP FOREIGN KEY FK_EA750E8166D1F9C');
        $this->addSql('ALTER TABLE task_label DROP FOREIGN KEY FK_C9034BC88DB60186');
        $this->addSql('ALTER TABLE task_label DROP FOREIGN KEY FK_C9034BC833B92F39');
        $this->addSql('DROP TABLE checklist_item');
        $this->addSql('DROP TABLE comment');
        $this->addSql('DROP TABLE label');
        $this->addSql('DROP TABLE task_label');
        $this->addSql('ALTER TABLE task DROP due_reminder_sent_at');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
