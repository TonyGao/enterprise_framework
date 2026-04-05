<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260405100300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add indexes for employee sortable fields to improve list performance';
    }

    public function up(Schema $schema): void
    {
        // Employee sortable fields
        $this->addSql('CREATE INDEX idx_employee_name ON org_employee (name)');
        $this->addSql('CREATE INDEX idx_employee_employment_status ON org_employee (employment_status)');
        $this->addSql('CREATE INDEX idx_employee_work_status ON org_employee (work_status)');
        $this->addSql('CREATE INDEX idx_employee_hire_date ON org_employee (hire_date)');
        $this->addSql('CREATE INDEX idx_employee_mobile ON org_employee (mobile)');
        $this->addSql('CREATE INDEX idx_employee_gender ON org_employee (gender)');
        $this->addSql('CREATE INDEX idx_employee_birth_date ON org_employee (birth_date)');
        $this->addSql('CREATE INDEX idx_employee_id_card ON org_employee (id_card)');
        $this->addSql('CREATE INDEX idx_employee_english_name ON org_employee (english_name)');

        // Position name for sorting
        $this->addSql('CREATE INDEX idx_position_name ON org_position (name)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_employee_name');
        $this->addSql('DROP INDEX idx_employee_employment_status');
        $this->addSql('DROP INDEX idx_employee_work_status');
        $this->addSql('DROP INDEX idx_employee_hire_date');
        $this->addSql('DROP INDEX idx_employee_mobile');
        $this->addSql('DROP INDEX idx_employee_gender');
        $this->addSql('DROP INDEX idx_employee_birth_date');
        $this->addSql('DROP INDEX idx_employee_id_card');
        $this->addSql('DROP INDEX idx_employee_english_name');
        $this->addSql('DROP INDEX idx_position_name');
    }
}
