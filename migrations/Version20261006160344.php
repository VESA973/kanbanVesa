<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261006160344 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE board_column (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(50) NOT NULL, position INT NOT NULL, project_id INT NOT NULL, INDEX IDX_BOARD_COLUMN_POSITION (project_id, position), INDEX IDX_D14DC3D9166D1F9C (project_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE task (id INT AUTO_INCREMENT NOT NULL, description LONGTEXT DEFAULT NULL, due_date DATE DEFAULT NULL, priority VARCHAR(10) NOT NULL, completed_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, title VARCHAR(255) NOT NULL, position INT NOT NULL, assignee_id INT DEFAULT NULL, column_id INT NOT NULL, created_by_id INT NOT NULL, INDEX IDX_TASK_POSITION (column_id, position), INDEX IDX_TASK_DUE_DATE (due_date), INDEX IDX_527EDB2559EC7D60 (assignee_id), INDEX IDX_527EDB25BE8E8ED5 (column_id), INDEX IDX_527EDB25B03A8386 (created_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE board_column ADD CONSTRAINT FK_D14DC3D9166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB2559EC7D60 FOREIGN KEY (assignee_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB25BE8E8ED5 FOREIGN KEY (column_id) REFERENCES board_column (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB25B03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE board_column DROP FOREIGN KEY FK_D14DC3D9166D1F9C');
        $this->addSql('ALTER TABLE task DROP FOREIGN KEY FK_527EDB2559EC7D60');
        $this->addSql('ALTER TABLE task DROP FOREIGN KEY FK_527EDB25BE8E8ED5');
        $this->addSql('ALTER TABLE task DROP FOREIGN KEY FK_527EDB25B03A8386');
        $this->addSql('DROP TABLE board_column');
        $this->addSql('DROP TABLE task');
    }
}
