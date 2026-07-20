<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260621153412 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Insert 系统管理员管理 menu item under 系统设置';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            DO \$\$
            DECLARE
                v_parent_id   UUID;
                v_parent_lft  INT;
                v_parent_rgt  INT;
                v_parent_lvl  INT;
                v_tree_root   UUID;
            BEGIN
                SELECT id, lft, rgt, lvl, tree_root
                INTO v_parent_id, v_parent_lft, v_parent_rgt, v_parent_lvl, v_tree_root
                FROM admin_menu
                WHERE menu_label = '系统设置';

                IF v_parent_id IS NULL THEN
                    RETURN;
                END IF;

                -- 如果已存在则跳过
                IF EXISTS (SELECT 1 FROM admin_menu WHERE menu_label = '系统管理员管理' AND parent_id = v_parent_id) THEN
                    RETURN;
                END IF;

                -- 腾出位置
                UPDATE admin_menu SET rgt = rgt + 2 WHERE rgt >= v_parent_rgt AND tree_root = v_tree_root;
                UPDATE admin_menu SET lft = lft + 2 WHERE lft > v_parent_rgt AND tree_root = v_tree_root;

                -- 插入菜单
                INSERT INTO admin_menu (
                    id, menu_label, menu_uri, menu_routeName, menu_url, icon, menu_type,
                    menu_description, lft, lvl, rgt, tree_root, parent_id, created_at, updated_at
                ) VALUES (
                    gen_random_uuid(),
                    '系统管理员管理',
                    '/admin/system-admin',
                    'admin_system_admin',
                    NULL,
                    'fa-solid fa-user-shield',
                    'system',
                    '系统管理员账号管理',
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
        $this->addSql("DELETE FROM admin_menu WHERE menu_label = '系统管理员管理'");
    }
}
