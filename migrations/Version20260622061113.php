<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260622061113 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE platform_view ADD entity_id UUID DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN platform_view.entity_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE platform_view ADD CONSTRAINT FK_7AF5CCA281257D5D FOREIGN KEY (entity_id) REFERENCES platform_entity (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_7AF5CCA281257D5D ON platform_view (entity_id)');
        $this->addSql('ALTER TABLE platform_view_field ADD config JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE platform_view_field DROP config');
        $this->addSql('ALTER TABLE platform_view DROP CONSTRAINT FK_7AF5CCA281257D5D');
        $this->addSql('DROP INDEX IDX_7AF5CCA281257D5D');
        $this->addSql('ALTER TABLE platform_view DROP entity_id');
    }
}
