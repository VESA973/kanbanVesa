<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261009102956 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'A task can have several assignees (task.assignee_id → task_assignee).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE task_assignee (task_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_3C5D16408DB60186 (task_id), INDEX IDX_3C5D1640A76ED395 (user_id), PRIMARY KEY (task_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE task_assignee ADD CONSTRAINT FK_3C5D16408DB60186 FOREIGN KEY (task_id) REFERENCES task (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE task_assignee ADD CONSTRAINT FK_3C5D1640A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('INSERT INTO task_assignee (task_id, user_id) SELECT id, assignee_id FROM task WHERE assignee_id IS NOT NULL');
        $this->addSql('ALTER TABLE task DROP FOREIGN KEY `FK_527EDB2559EC7D60`');
        $this->addSql('DROP INDEX IDX_527EDB2559EC7D60 ON task');
        $this->addSql('ALTER TABLE task DROP assignee_id');
    }

    /**
     * Only one assignee per task survives going back: the first one by id.
     */
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task ADD assignee_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT `FK_527EDB2559EC7D60` FOREIGN KEY (assignee_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_527EDB2559EC7D60 ON task (assignee_id)');
        $this->addSql('UPDATE task t SET t.assignee_id = (SELECT MIN(ta.user_id) FROM task_assignee ta WHERE ta.task_id = t.id)');
        $this->addSql('ALTER TABLE task_assignee DROP FOREIGN KEY FK_3C5D16408DB60186');
        $this->addSql('ALTER TABLE task_assignee DROP FOREIGN KEY FK_3C5D1640A76ED395');
        $this->addSql('DROP TABLE task_assignee');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
