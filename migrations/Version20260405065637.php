<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260405065637 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add indexes for department and position sorting performance';
    }

    public function up(Schema $schema): void
    {
        // Add index on department_id for faster department sorting
        $this->addSql('CREATE INDEX idx_employee_department_id ON org_employee (department_id)');

        // Add index on position_id for faster position sorting
        $this->addSql('CREATE INDEX idx_employee_position_id ON org_employee (position_id)');

        // Add index on department name for faster sorting
        $this->addSql('CREATE INDEX idx_department_name ON org_department (name)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_employee_department_id');
        $this->addSql('DROP INDEX idx_employee_position_id');
        $this->addSql('DROP INDEX idx_department_name');
    }
}
