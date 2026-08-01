<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260801114042 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add intent column to platform_ai_chat_session';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE platform_ai_chat_session ADD intent VARCHAR(64) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE platform_ai_chat_session DROP intent');
    }
}
