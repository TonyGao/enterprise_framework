<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260419000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create sys_calendar_event table and insert 系统日历 menu item under 系统配置';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            CREATE TABLE sys_calendar_event (
                id UUID NOT NULL,
                title VARCHAR(255) NOT NULL,
                type VARCHAR(32) NOT NULL DEFAULT 'custom',
                date DATE DEFAULT NULL,
                recurring BOOLEAN NOT NULL DEFAULT FALSE,
                recurring_rule JSONB DEFAULT NULL,
                note TEXT DEFAULT NULL,
                color VARCHAR(30) DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
        ");

        $this->addSql("COMMENT ON COLUMN sys_calendar_event.type IS 'holiday|workday|makeup_day|custom'");
        $this->addSql("CREATE INDEX idx_cal_event_date ON sys_calendar_event (date)");
        $this->addSql("CREATE INDEX idx_cal_event_type ON sys_calendar_event (type)");
        $this->addSql("CREATE INDEX idx_cal_event_recurring ON sys_calendar_event (recurring)");

        // 在"系统配置"（或"系统设置"）菜单下插入"系统日历"菜单项
        // 先尝试"系统配置"，不存在则不插入（避免报错）
        $this->addSql("
            DO \$\$
            DECLARE
                v_parent_id   UUID;
                v_parent_lft  INT;
                v_parent_rgt  INT;
                v_parent_lvl  INT;
                v_tree_root   UUID;
            BEGIN
                -- 找到父菜单（优先\"系统配置\"，其次\"系统设置\"）
                SELECT id, lft, rgt, lvl, tree_root
                INTO v_parent_id, v_parent_lft, v_parent_rgt, v_parent_lvl, v_tree_root
                FROM admin_menu
                WHERE menu_label IN ('系统配置', '系统设置')
                ORDER BY (CASE WHEN menu_label = '系统配置' THEN 0 ELSE 1 END)
                LIMIT 1;

                IF v_parent_id IS NULL THEN
                    RETURN; -- 父菜单不存在，跳过
                END IF;

                -- 腾出位置：将父菜单右侧的所有节点 rgt+2
                UPDATE admin_menu SET rgt = rgt + 2 WHERE rgt >= v_parent_rgt AND tree_root = v_tree_root;
                UPDATE admin_menu SET lft = lft + 2 WHERE lft > v_parent_rgt AND tree_root = v_tree_root;

                -- 插入新菜单项（紧靠父菜单右内侧）
                INSERT INTO admin_menu (
                    id, menu_label, menu_uri, menu_routeName, menu_url, icon, menu_type,
                    menu_description, lft, lvl, rgt, tree_root, parent_id, created_at, updated_at
                ) VALUES (
                    gen_random_uuid(),
                    '系统日历',
                    '/admin/calendar',
                    'admin_calendar_index',
                    NULL,
                    'fa-solid fa-calendar-days',
                    'system',
                    '假日与工作日日历管理',
                    v_parent_rgt,
                    v_parent_lvl + 1,
                    v_parent_rgt + 1,
                    v_tree_root,
                    v_parent_id,
                    NOW(),
                    NOW()
                );
            END
            \$\$
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DROP TABLE IF EXISTS sys_calendar_event");
        $this->addSql("DELETE FROM admin_menu WHERE menu_routeName = 'admin_calendar_index'");
    }
}
