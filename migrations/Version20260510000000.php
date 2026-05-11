<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260510000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create sys_calendar_event_type table, seed calendar event types, and import any legacy calendar_types.json data';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE sys_calendar_event_type (
                id UUID NOT NULL,
                code VARCHAR(32) NOT NULL,
                label VARCHAR(100) NOT NULL,
                color VARCHAR(30) NOT NULL,
                icon VARCHAR(50) NOT NULL,
                is_system BOOLEAN NOT NULL DEFAULT FALSE,
                sort_order INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
        SQL);

        $this->addSql('CREATE UNIQUE INDEX uniq_cal_event_type_code ON sys_calendar_event_type (code)');
        $this->addSql('CREATE INDEX idx_cal_event_type_sort ON sys_calendar_event_type (sort_order)');
        $this->addSql('CREATE INDEX idx_cal_event_type_system ON sys_calendar_event_type (is_system)');

        $this->addSql(<<<'SQL'
            INSERT INTO sys_calendar_event_type (id, code, label, color, icon, is_system, sort_order, created_at)
            VALUES
                (gen_random_uuid(), 'holiday', '法定假日', '#dc2626', 'fa-solid fa-umbrella-beach', TRUE, 10, NOW()),
                (gen_random_uuid(), 'workday', '补班上班', '#ea580c', 'fa-solid fa-briefcase', TRUE, 20, NOW()),
                (gen_random_uuid(), 'makeup_day', '调休休息', '#2563eb', 'fa-solid fa-bed', TRUE, 30, NOW()),
                (gen_random_uuid(), 'company_event', '公司活动', '#8b5cf6', 'fa-solid fa-users', FALSE, 40, NOW()),
                (gen_random_uuid(), 'training', '公司培训', '#0891b2', 'fa-solid fa-chalkboard-teacher', FALSE, 50, NOW()),
                (gen_random_uuid(), 'anniversary', '纪念日', '#e11d48', 'fa-solid fa-cake-candles', FALSE, 60, NOW()),
                (gen_random_uuid(), 'custom', '自定义事务', '#6b7280', 'fa-solid fa-tag', TRUE, 70, NOW())
        SQL);

        $this->importLegacyFileTypes();
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS sys_calendar_event_type');
    }

    private function importLegacyFileTypes(): void
    {
        $legacyPath = dirname(__DIR__) . '/var/calendar_types.json';
        if (!is_file($legacyPath)) {
            return;
        }

        $content = file_get_contents($legacyPath);
        $rows = json_decode($content ?: '', true);
        if (!is_array($rows)) {
            return;
        }

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $code = strtolower(trim((string) ($row['key'] ?? $row['code'] ?? '')));
            $label = trim((string) ($row['label'] ?? ''));
            if ($code === '' || $label === '') {
                continue;
            }

            $this->addSql(
                'INSERT INTO sys_calendar_event_type (id, code, label, color, icon, is_system, sort_order, created_at) VALUES (gen_random_uuid(), :code, :label, :color, :icon, :isSystem, :sortOrder, NOW()) ON CONFLICT (code) DO NOTHING',
                [
                    'code' => $code,
                    'label' => $label,
                    'color' => trim((string) ($row['color'] ?? '#6b7280')) ?: '#6b7280',
                    'icon' => trim((string) ($row['icon'] ?? 'fa-solid fa-tag')) ?: 'fa-solid fa-tag',
                    'isSystem' => false,
                    'sortOrder' => isset($row['sort']) ? (int) $row['sort'] : 999,
                ]
            );
        }
    }
}