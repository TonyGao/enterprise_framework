<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * 邮件模板多语言支持 / Multi-language support for email templates.
 *
 * 为 sys_email_template 增加 locale 列，唯一索引由 code 改为 (code, locale)，
 * 使同一模板可有多语言版本。不夹带无关 schema 漂移。
 * Adds a locale column to sys_email_template and changes the unique index from
 * (code) to (code, locale) so one template can have multiple localized versions.
 */
final class Version20260805054947 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add locale to email templates (multi-language)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sys_email_template ADD locale VARCHAR(16) DEFAULT \'zh_CN\' NOT NULL');
        $this->addSql('DROP INDEX uniq_20f5958077153098');
        $this->addSql('CREATE UNIQUE INDEX uniq_email_template_code_locale ON sys_email_template (code, locale)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_email_template_code_locale');
        $this->addSql('ALTER TABLE sys_email_template DROP locale');
        $this->addSql('CREATE UNIQUE INDEX uniq_20f5958077153098 ON sys_email_template (code)');
    }
}
