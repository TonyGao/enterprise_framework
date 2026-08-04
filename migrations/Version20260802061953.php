<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260802061953 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add mode column to platform_ai_chat_session';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE platform_ai_chat_session ADD mode VARCHAR(16) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE platform_ai_chat_session DROP mode');
    }
}
