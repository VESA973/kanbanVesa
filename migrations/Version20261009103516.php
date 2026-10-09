<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261009103516 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Members of a chantier or a program can be made « responsable » (is_lead).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE program_member ADD is_lead TINYINT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE project_member ADD is_lead TINYINT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE program_member DROP is_lead');
        $this->addSql('ALTER TABLE project_member DROP is_lead');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
