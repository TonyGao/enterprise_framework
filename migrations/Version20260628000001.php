<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260628000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '为 platform_llm_provider 和 platform_llm_role 补充缺失的 CommonTrait 字段 (deleted_at, created_by, updated_by)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE platform_llm_provider
            ADD deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            ADD created_by VARCHAR(255) DEFAULT NULL,
            ADD updated_by VARCHAR(255) DEFAULT NULL');

        $this->addSql('ALTER TABLE platform_llm_role
            ADD deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            ADD created_by VARCHAR(255) DEFAULT NULL,
            ADD updated_by VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE platform_llm_provider
            DROP deleted_at, DROP created_by, DROP updated_by');
        $this->addSql('ALTER TABLE platform_llm_role
            DROP deleted_at, DROP created_by, DROP updated_by');
    }
}
