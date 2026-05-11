<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260413000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create sys_task and sys_task_log tables for scheduled task system';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            CREATE TABLE sys_task (
                id UUID NOT NULL,
                name VARCHAR(255) NOT NULL,
                description TEXT DEFAULT NULL,
                cron_expression VARCHAR(100) NOT NULL,
                handler VARCHAR(255) NOT NULL,
                payload JSONB NOT NULL DEFAULT '{}',
                enabled BOOLEAN NOT NULL DEFAULT TRUE,
                last_run_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                next_run_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                max_retries SMALLINT NOT NULL DEFAULT 3,
                timeout INT NOT NULL DEFAULT 0,
                sort_order INT NOT NULL DEFAULT 0,
                category VARCHAR(64) DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
        ");

        $this->addSql('CREATE INDEX idx_task_next_run_at ON sys_task (next_run_at)');
        $this->addSql('CREATE INDEX idx_task_enabled ON sys_task (enabled)');
        $this->addSql('CREATE INDEX idx_task_due ON sys_task (enabled, next_run_at)');

        $this->addSql("
            CREATE TABLE sys_task_log (
                id UUID NOT NULL,
                task_id UUID NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'running',
                output TEXT DEFAULT NULL,
                execution_ms INT DEFAULT NULL,
                memory_peak BIGINT DEFAULT NULL,
                host_name VARCHAR(100) DEFAULT NULL,
                started_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                finished_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                PRIMARY KEY(id)
            )
        ");

        $this->addSql('CREATE INDEX idx_task_log_task_id  ON sys_task_log (task_id)');
        $this->addSql('CREATE INDEX idx_task_log_status   ON sys_task_log (status)');
        $this->addSql('CREATE INDEX idx_task_log_started  ON sys_task_log (started_at)');
        $this->addSql('ALTER TABLE sys_task_log ADD CONSTRAINT fk_task_log_task FOREIGN KEY (task_id) REFERENCES sys_task (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        // 插入"定时任务"菜单（在系统设置下）
        $this->addSql("
            INSERT INTO admin_menu (id, menu_label, menu_uri, menu_routeName, menu_url, icon, menu_type, menu_description, lft, lvl, rgt, tree_root, parent_id, created_at, updated_at)
            SELECT
                gen_random_uuid(),
                '定时任务',
                '/admin/task',
                'admin_task_index',
                NULL,
                'fa-solid fa-clock-rotate-left',
                'system',
                '系统定时任务管理',
                (SELECT rgt FROM admin_menu WHERE menu_label = '系统设置'),
                (SELECT lvl + 1 FROM admin_menu WHERE menu_label = '系统设置'),
                (SELECT rgt FROM admin_menu WHERE menu_label = '系统设置') + 1,
                (SELECT tree_root FROM admin_menu WHERE menu_label = '系统设置'),
                (SELECT id FROM admin_menu WHERE menu_label = '系统设置'),
                NOW(),
                NOW()
            WHERE EXISTS (SELECT 1 FROM admin_menu WHERE menu_label = '系统设置')
        ");

        // 更新受影响节点的 lft/rgt（Nested Set 树调整）
        $this->addSql("
            UPDATE admin_menu
            SET rgt = rgt + 2
            WHERE rgt >= (
                SELECT rgt - 2 FROM admin_menu WHERE menu_label = '定时任务'
            )
            AND menu_label != '定时任务'
        ");

        $this->addSql("
            UPDATE admin_menu
            SET lft = lft + 2
            WHERE lft > (
                SELECT lft FROM admin_menu WHERE menu_label = '系统设置'
            )
            AND menu_label NOT IN ('系统设置', '定时任务')
            AND lft >= (SELECT lft FROM admin_menu WHERE menu_label = '定时任务')
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM admin_menu WHERE menu_label = '定时任务'");
        $this->addSql('DROP TABLE IF EXISTS sys_task_log');
        $this->addSql('DROP TABLE IF EXISTS sys_task');
    }
}
