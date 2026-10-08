<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Programs ("projets globaux") grouping projects.
 *
 * Existing projects go to a "Général" program of their owner (one per owner, so nobody
 * gains access to someone else's projects). down() removes programs, their invitations
 * and the inherited project memberships; projects and tasks are untouched.
 */
final class Version20261008104417 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add programs grouping projects; existing projects go to a "Général" program of their owner';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE program (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, description LONGTEXT DEFAULT NULL, color VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, owner_id INT NOT NULL, INDEX IDX_92ED77847E3C61F9 (owner_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE program_member (id INT AUTO_INCREMENT NOT NULL, joined_at DATETIME NOT NULL, role VARCHAR(20) NOT NULL, program_id INT NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_PROGRAM_MEMBER (program_id, user_id), INDEX IDX_24A4C4223EB8070A (program_id), INDEX IDX_24A4C422A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE program ADD CONSTRAINT FK_92ED77847E3C61F9 FOREIGN KEY (owner_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE program_member ADD CONSTRAINT FK_24A4C4223EB8070A FOREIGN KEY (program_id) REFERENCES program (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE program_member ADD CONSTRAINT FK_24A4C422A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE invitation ADD program_id INT DEFAULT NULL, CHANGE project_id project_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE invitation ADD CONSTRAINT FK_F11D61A23EB8070A FOREIGN KEY (program_id) REFERENCES program (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_F11D61A23EB8070A ON invitation (program_id)');
        $this->addSql('ALTER TABLE project_member ADD inherited TINYINT DEFAULT 0 NOT NULL');

        // One "Général" program per project owner, owned by them.
        $this->addSql("INSERT INTO program (name, description, color, created_at, owner_id) SELECT 'Général', NULL, 'indigo', NOW(), owner_id FROM project GROUP BY owner_id");
        $this->addSql("INSERT INTO program_member (program_id, user_id, role, joined_at) SELECT id, owner_id, 'owner', NOW() FROM program");

        // Nullable first so existing projects can be placed, then made mandatory.
        $this->addSql('ALTER TABLE project ADD program_id INT DEFAULT NULL');
        $this->addSql('UPDATE project p INNER JOIN program g ON g.owner_id = p.owner_id SET p.program_id = g.id');
        $this->addSql('ALTER TABLE project MODIFY program_id INT NOT NULL');
        $this->addSql('ALTER TABLE project ADD CONSTRAINT FK_2FB3D0EE3EB8070A FOREIGN KEY (program_id) REFERENCES program (id)');
        $this->addSql('CREATE INDEX IDX_2FB3D0EE3EB8070A ON project (program_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project DROP FOREIGN KEY FK_2FB3D0EE3EB8070A');
        $this->addSql('DROP INDEX IDX_2FB3D0EE3EB8070A ON project');
        $this->addSql('ALTER TABLE project DROP program_id');

        // Access given by a program disappears with it (it must not turn into a direct membership).
        $this->addSql('DELETE FROM project_member WHERE inherited = 1');
        $this->addSql('ALTER TABLE project_member DROP inherited');

        $this->addSql('DELETE FROM invitation WHERE project_id IS NULL');
        $this->addSql('ALTER TABLE invitation DROP FOREIGN KEY FK_F11D61A23EB8070A');
        $this->addSql('DROP INDEX IDX_F11D61A23EB8070A ON invitation');
        $this->addSql('ALTER TABLE invitation DROP program_id, CHANGE project_id project_id INT NOT NULL');

        $this->addSql('ALTER TABLE program_member DROP FOREIGN KEY FK_24A4C4223EB8070A');
        $this->addSql('ALTER TABLE program_member DROP FOREIGN KEY FK_24A4C422A76ED395');
        $this->addSql('ALTER TABLE program DROP FOREIGN KEY FK_92ED77847E3C61F9');
        $this->addSql('DROP TABLE program_member');
        $this->addSql('DROP TABLE program');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
