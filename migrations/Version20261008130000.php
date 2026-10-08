<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Data tables inside tasks (a team, a list of tools…): typed columns, rows with JSON cells.
 */
final class Version20261008130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add data tables to tasks';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE task_table (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(100) NOT NULL, position INT NOT NULL, task_id INT NOT NULL, INDEX IDX_318D94668DB60186 (task_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE task_table_column (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(50) NOT NULL, type VARCHAR(20) NOT NULL, position INT NOT NULL, table_id INT NOT NULL, INDEX IDX_EB10A690ECFF285C (table_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE task_table_row (id INT AUTO_INCREMENT NOT NULL, cells JSON NOT NULL, position INT NOT NULL, table_id INT NOT NULL, INDEX IDX_F2FFB1EEECFF285C (table_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE task_table ADD CONSTRAINT FK_318D94668DB60186 FOREIGN KEY (task_id) REFERENCES task (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE task_table_column ADD CONSTRAINT FK_EB10A690ECFF285C FOREIGN KEY (table_id) REFERENCES task_table (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE task_table_row ADD CONSTRAINT FK_F2FFB1EEECFF285C FOREIGN KEY (table_id) REFERENCES task_table (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task_table DROP FOREIGN KEY FK_318D94668DB60186');
        $this->addSql('ALTER TABLE task_table_column DROP FOREIGN KEY FK_EB10A690ECFF285C');
        $this->addSql('ALTER TABLE task_table_row DROP FOREIGN KEY FK_F2FFB1EEECFF285C');
        $this->addSql('DROP TABLE task_table_row');
        $this->addSql('DROP TABLE task_table_column');
        $this->addSql('DROP TABLE task_table');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
