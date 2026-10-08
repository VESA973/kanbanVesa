<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Personal poles (a heading to sort one's programs) and the programs filed in them.
 */
final class Version20261008140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add personal poles to sort programs';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE pole (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(50) NOT NULL, position INT NOT NULL, owner_id INT NOT NULL, INDEX IDX_POLE_POSITION (owner_id, position), INDEX IDX_FD6042E17E3C61F9 (owner_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE pole_program (pole_id INT NOT NULL, program_id INT NOT NULL, INDEX IDX_C21B4CE0419C3385 (pole_id), INDEX IDX_C21B4CE03EB8070A (program_id), PRIMARY KEY (pole_id, program_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE pole ADD CONSTRAINT FK_FD6042E17E3C61F9 FOREIGN KEY (owner_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE pole_program ADD CONSTRAINT FK_C21B4CE0419C3385 FOREIGN KEY (pole_id) REFERENCES pole (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE pole_program ADD CONSTRAINT FK_C21B4CE03EB8070A FOREIGN KEY (program_id) REFERENCES program (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pole DROP FOREIGN KEY FK_FD6042E17E3C61F9');
        $this->addSql('ALTER TABLE pole_program DROP FOREIGN KEY FK_C21B4CE0419C3385');
        $this->addSql('ALTER TABLE pole_program DROP FOREIGN KEY FK_C21B4CE03EB8070A');
        $this->addSql('DROP TABLE pole_program');
        $this->addSql('DROP TABLE pole');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
