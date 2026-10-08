<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Categories inside projects. Every existing project gets a "Général" category holding all its tasks,
 * so no task is lost; down() removes the categories and leaves the tasks untouched.
 */
final class Version20261008101125 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add categories to projects and attach existing tasks to a default "Général" category';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE category (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(50) NOT NULL, position INT NOT NULL, project_id INT NOT NULL, INDEX IDX_CATEGORY_POSITION (project_id, position), INDEX IDX_64C19C1166D1F9C (project_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE category ADD CONSTRAINT FK_64C19C1166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql("INSERT INTO category (project_id, name, position) SELECT id, 'Général', 0 FROM project");

        // Nullable first so existing rows can be filled in, then made mandatory.
        $this->addSql('ALTER TABLE task ADD category_id INT DEFAULT NULL');
        $this->addSql('UPDATE task t INNER JOIN board_column c ON c.id = t.column_id INNER JOIN category cat ON cat.project_id = c.project_id SET t.category_id = cat.id');
        $this->addSql('ALTER TABLE task MODIFY category_id INT NOT NULL');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB2512469DE2 FOREIGN KEY (category_id) REFERENCES category (id)');
        $this->addSql('CREATE INDEX IDX_527EDB2512469DE2 ON task (category_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task DROP FOREIGN KEY FK_527EDB2512469DE2');
        $this->addSql('DROP INDEX IDX_527EDB2512469DE2 ON task');
        $this->addSql('ALTER TABLE task DROP category_id');
        $this->addSql('ALTER TABLE category DROP FOREIGN KEY FK_64C19C1166D1F9C');
        $this->addSql('DROP TABLE category');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
