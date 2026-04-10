<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260406000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add force_password_reset column to org_employee table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE org_employee ADD COLUMN force_password_reset BOOLEAN DEFAULT true NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE org_employee DROP COLUMN force_password_reset');
    }
}
