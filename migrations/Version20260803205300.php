<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260803205300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add meta column to platform_ai_chat_message';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE platform_ai_chat_message ADD meta JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE platform_ai_chat_message DROP meta');
    }
}
