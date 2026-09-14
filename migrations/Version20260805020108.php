<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * 组织实体国际化字段 / Internationalization fields for org entities.
 *
 * 为 org_company / org_corporation 增加国家、默认语言、默认币种列，
 * 支撑多国家/多币种业务。不夹带无关 schema 漂移。
 * Adds country / default locale / default currency columns to org_company and
 * org_corporation for multi-country / multi-currency support. No unrelated drift.
 */
final class Version20260805020108 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add i18n columns to org_company and org_corporation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE org_company ADD country_code VARCHAR(2) DEFAULT NULL');
        $this->addSql('ALTER TABLE org_company ADD default_locale VARCHAR(16) DEFAULT NULL');
        $this->addSql('ALTER TABLE org_company ADD default_currency VARCHAR(3) DEFAULT NULL');
        $this->addSql('ALTER TABLE org_corporation ADD country_code VARCHAR(2) DEFAULT NULL');
        $this->addSql('ALTER TABLE org_corporation ADD default_locale VARCHAR(16) DEFAULT NULL');
        $this->addSql('ALTER TABLE org_corporation ADD default_currency VARCHAR(3) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE org_company DROP COLUMN country_code');
        $this->addSql('ALTER TABLE org_company DROP COLUMN default_locale');
        $this->addSql('ALTER TABLE org_company DROP COLUMN default_currency');
        $this->addSql('ALTER TABLE org_corporation DROP COLUMN country_code');
        $this->addSql('ALTER TABLE org_corporation DROP COLUMN default_locale');
        $this->addSql('ALTER TABLE org_corporation DROP COLUMN default_currency');
    }
}
