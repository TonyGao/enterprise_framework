<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * 国际化参考数据表：币种与国家 / Internationalization reference tables: currency & country.
 *
 * 仅包含本次新增的 platform_currency 与 platform_country 建表；
 * 避免夹带与本次改动无关的存量 schema 漂移。
 * Only creates the new platform_currency and platform_country tables;
 * unrelated pre-existing schema drift is intentionally excluded.
 */
final class Version20260805015523 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create currency & country reference tables (i18n)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE platform_country (id UUID NOT NULL, owner_corporation_id UUID DEFAULT NULL, owner_company_id UUID DEFAULT NULL, code VARCHAR(2) NOT NULL, name VARCHAR(100) NOT NULL, locale VARCHAR(16) DEFAULT \'zh_CN\' NOT NULL, currency_code VARCHAR(3) DEFAULT NULL, phone_code VARCHAR(16) DEFAULT NULL, timezone VARCHAR(64) DEFAULT NULL, enabled BOOLEAN DEFAULT true NOT NULL, order_num INT DEFAULT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_by VARCHAR(255) DEFAULT NULL, updated_by VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_4643033477153098 ON platform_country (code)');
        $this->addSql('CREATE INDEX IDX_46430334F617CBEC ON platform_country (owner_corporation_id)');
        $this->addSql('CREATE INDEX IDX_46430334C5F18393 ON platform_country (owner_company_id)');
        $this->addSql('COMMENT ON COLUMN platform_country.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN platform_country.owner_corporation_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN platform_country.owner_company_id IS \'(DC2Type:uuid)\'');

        $this->addSql('CREATE TABLE platform_currency (id UUID NOT NULL, owner_corporation_id UUID DEFAULT NULL, owner_company_id UUID DEFAULT NULL, code VARCHAR(3) NOT NULL, symbol VARCHAR(8) NOT NULL, decimals INT DEFAULT 2 NOT NULL, enabled BOOLEAN DEFAULT true NOT NULL, order_num INT DEFAULT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_by VARCHAR(255) DEFAULT NULL, updated_by VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_EC26882D77153098 ON platform_currency (code)');
        $this->addSql('CREATE INDEX IDX_EC26882DF617CBEC ON platform_currency (owner_corporation_id)');
        $this->addSql('CREATE INDEX IDX_EC26882DC5F18393 ON platform_currency (owner_company_id)');
        $this->addSql('COMMENT ON COLUMN platform_currency.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN platform_currency.owner_corporation_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN platform_currency.owner_company_id IS \'(DC2Type:uuid)\'');

        $this->addSql('ALTER TABLE platform_country ADD CONSTRAINT FK_46430334F617CBEC FOREIGN KEY (owner_corporation_id) REFERENCES org_corporation (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE platform_country ADD CONSTRAINT FK_46430334C5F18393 FOREIGN KEY (owner_company_id) REFERENCES org_company (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE platform_currency ADD CONSTRAINT FK_EC26882DF617CBEC FOREIGN KEY (owner_corporation_id) REFERENCES org_corporation (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE platform_currency ADD CONSTRAINT FK_EC26882DC5F18393 FOREIGN KEY (owner_company_id) REFERENCES org_company (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE platform_country');
        $this->addSql('DROP TABLE platform_currency');
    }
}
