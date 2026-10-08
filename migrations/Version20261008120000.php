<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Optional illustration of a program (the file lives in var/uploads/<env>/programs).
 */
final class Version20261008120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add an optional image to programs';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE program ADD image_filename VARCHAR(64) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE program DROP image_filename');
    }
}
