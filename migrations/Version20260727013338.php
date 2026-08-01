<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Rename page_id to context_id in ai chat session table
 */
final class Version20260727013338 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '重命名 platform_ai_chat_session 表的 page_id 列为 context_id';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE platform_ai_chat_session RENAME COLUMN page_id TO context_id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE platform_ai_chat_session RENAME COLUMN context_id TO page_id');
    }
}
