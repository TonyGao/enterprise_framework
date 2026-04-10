<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260406000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add default_password and force_reset_password_on_first_login columns to security_password_policy table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE security_password_policy ADD COLUMN default_password VARCHAR(255) DEFAULT \'Welcome@2024\' NOT NULL');
        $this->addSql('ALTER TABLE security_password_policy ADD COLUMN force_reset_password_on_first_login BOOLEAN DEFAULT true NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE security_password_policy DROP COLUMN default_password');
        $this->addSql('ALTER TABLE security_password_policy DROP COLUMN force_reset_password_on_first_login');
    }
}
