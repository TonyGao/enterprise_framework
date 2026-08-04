<?php

namespace App\Command;

use App\Entity\Organization\Employee;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * 通过 Chrome CDP 自动登录并截图，保存到官网 doggyoa-site/public/screenshots/。
 *
 * 前置条件：Chrome 已开启远程调试 + CDP 代理已启动
 *   php bin/console ef:chrome:open
 *
 * 用法示例：
 *   php bin/console ef:docs:screenshot --username admin --password 'xxx' --target dashboard
 *   php bin/console ef:docs:screenshot --login --mark-password-modified --all
 */
#[AsCommand(
    name: 'ef:docs:screenshot',
    description: '通过 Chrome CDP 自动登录并截图，保存到官网 public/screenshots/',
)]
class EfDocsScreenshotCommand extends Command
{
    private const PROXY_HOST = '127.0.0.1';
    private const PROXY_PORT = 9223;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('site', null, InputOption::VALUE_REQUIRED, '官网项目路径（保存截图的目标目录）', $this->defaultSitePath())
            ->addOption('base-url', null, InputOption::VALUE_REQUIRED, '应用基础地址', 'http://localhost:8000')
            ->addOption('username', 'u', InputOption::VALUE_REQUIRED, '登录用户名（需要登录时）', 'sys_admin')
            ->addOption('password', 'p', InputOption::VALUE_REQUIRED, '登录密码（需要登录时）')
            ->addOption('login', 'l', InputOption::VALUE_NONE, '先执行自动登录再截图')
            ->addOption('mark-password-modified', 'm', InputOption::VALUE_NONE, '登录成功后将该用户的 is_password_modified_by_user 置为 true（跳过首次登录设置引导）')
            ->addOption('target', 't', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, '要截图的页面标识（可用多次），见 --list')
            ->addOption('all', 'a', InputOption::VALUE_NONE, '截图全部内置页面')
            ->addOption('list', null, InputOption::VALUE_NONE, '列出内置截图页面清单')
            ->addOption('wait', null, InputOption::VALUE_REQUIRED, '导航后等待秒数', '1.5')
            ->addOption('delay', null, InputOption::VALUE_REQUIRED, '两张截图之间间隔秒数', '0.5');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($input->getOption('list')) {
            $this->listTargets($io);

            return Command::SUCCESS;
        }

        $targets = $this->targets();
        if ($input->getOption('all')) {
            $selected = array_keys($targets);
        } else {
            $selected = $input->getOption('target');
            if (0 === count($selected)) {
                $io->error('请指定 --target 或用 --all 截图全部页面，可用 --list 查看清单');

                return Command::FAILURE;
            }
            foreach ($selected as $t) {
                if (!isset($targets[$t])) {
                    $io->error("未知页面标识: $t，可用 --list 查看");

                    return Command::FAILURE;
                }
            }
        }

        $sitePath = rtrim($input->getOption('site'), '/');
        $outDir = $sitePath.'/public/screenshots';
        if (!is_dir($outDir) && !mkdir($outDir, 0777, true) && !is_dir($outDir)) {
            $io->error("无法创建输出目录: $outDir");

            return Command::FAILURE;
        }
        $io->note("截图输出目录: $outDir");

        // 连接检查
        if (!$this->cdpConnected()) {
            $io->error('无法连接 CDP 代理 (127.0.0.1:9223)，请先运行: php bin/console ef:chrome:open');

            return Command::FAILURE;
        }
        $io->success('已连接 CDP 代理');

        $baseUrl = rtrim($input->getOption('base-url'), '/');

        // 登录（可选）
        if ($input->getOption('login')) {
            $username = $input->getOption('username');
            $password = $input->getOption('password');
            if (!$password) {
                $io->error('使用 --login 时必须提供 --password');

                return Command::FAILURE;
            }
            $ok = $this->login($baseUrl, $username, $password);
            if (!$ok) {
                $io->error('自动登录失败，请检查凭据');

                return Command::FAILURE;
            }
            $io->success("登录成功: $username");

            // 跳过首次登录设置引导（改密码 + 注册 passkey 的强制流程）
            if ($input->getOption('mark-password-modified')) {
                $employee = $this->entityManager->getRepository(Employee::class)
                    ->findOneBy(['username' => $username]);
                if ($employee instanceof Employee && !$employee->getIsPasswordModifiedByUser()) {
                    $employee->setIsPasswordModifiedByUser(true);
                    $this->entityManager->flush();
                    $io->note("已将 {$username} 标记为密码已修改，跳过首次登录引导");
                }
            }
        }

        $wait = (float) $input->getOption('wait');
        $delay = (float) $input->getOption('delay');
        $lastKey = end($selected);
        reset($selected);

        $saved = [];
        foreach ($selected as $key) {
            $t = $targets[$key];
            $url = $this->resolvePlaceholders($t['url']);
            if (!str_starts_with($url, 'http')) {
                $url = $baseUrl.$url;
            }
            $io->section("[{$key}] {$url}");

            $this->cdpSend('Page.navigate', ['url' => $url]);
            usleep((int) ($wait * 1000000));

            // 页面就绪等待
            $this->waitForLoad();

            // 检测是否被首次登录引导重定向（密码未改 / 无 passkey）
            $currentPath = (string) ($this->cdpEval('window.location.pathname', true) ?? '');
            if ('/user/setup' === $currentPath) {
                $io->warning("[{$key}] 被重定向到 /user/setup（该账号未完成首次设置：修改密码 + 注册 Passkey）");
                $io->text('请在 Chrome 中手动完成一次首次设置后重试，或用已完成设置的账号运行: --username <已就绪账号> --login');
                continue;
            }

            // 检测异常页 / 404 页（Symfony 异常页 / 未找到页面）
            $exceptionHint = (string) ($this->cdpEval(
                'document.querySelector(".exception-message, .text-exception, h1:has(.status-code), .symfony-error, .status-404")?.innerText?.trim() ?? ""',
                true
            ) ?? '');
            if (str_contains($currentPath, '/error') || str_contains((string) $exceptionHint, '404') || str_contains((string) $exceptionHint, 'An error occurred')) {
                $io->warning("[{$key}] 页面异常或 404（{$currentPath}）: ".mb_substr($exceptionHint, 0, 80));
                $io->text('请检查路由是否正确（php bin/console debug:router）后重试');
                continue;
            }

            $filename = ($t['filename'] ?? $key).'.png';
            $savePath = $outDir.'/'.$filename;
            $shot = $this->screenshotToFile($savePath, $t['selector'] ?? null, $t['fullPage'] ?? false);

            if (isset($shot['error'])) {
                $io->warning("[{$key}] 截图失败: {$shot['error']}");
                continue;
            }
            $io->success("已保存: {$filename} ({$shot['bytes']} bytes)");
            $saved[] = $filename;

            if ($delay > 0 && $key !== $lastKey) {
                usleep((int) ($delay * 1000000));
            }
        }

        $io->newLine();
        $io->success('完成。共保存 '.count($saved).' 张截图到: '.$outDir);

        // 生成 manifest
        if (count($saved) > 0) {
            $manifest = [];
            foreach ($selected as $key) {
                $t = $targets[$key];
                $filename = ($t['filename'] ?? $key).'.png';
                $manifest[$key] = [
                    'file' => 'screenshots/'.$filename,
                    'url' => $t['url'],
                    'desc' => $t['desc'] ?? '',
                ];
            }
            file_put_contents($outDir.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $io->note('已生成 manifest.json');
        }

        return Command::SUCCESS;
    }

    // ---------- CDP 客户端 ----------

    private function cdpConnected(): bool
    {
        $fp = @stream_socket_client(sprintf('tcp://%s:%d', self::PROXY_HOST, self::PROXY_PORT), $e, $s, 2);
        if (!$fp) {
            return false;
        }
        fclose($fp);

        return true;
    }

    private function cdpSend(string $method, array $params = [], int $timeout = 60000): array
    {
        $payload = json_encode([
            'method' => $method,
            'params' => $params,
            'timeout' => $timeout,
        ])."\n";

        $fp = @stream_socket_client(sprintf('tcp://%s:%d', self::PROXY_HOST, self::PROXY_PORT), $errno, $errstr, 5);
        if (!$fp) {
            throw new \RuntimeException('无法连接 CDP 代理，请先运行 php bin/console ef:chrome:open');
        }
        stream_set_timeout($fp, ceil($timeout / 1000) + 5);
        fwrite($fp, $payload);

        $response = '';
        while (!feof($fp)) {
            $chunk = fgets($fp, 65536);
            if (false === $chunk) {
                break;
            }
            $response .= $chunk;
            if (str_ends_with(trim($chunk), '}')) {
                break;
            }
        }
        fclose($fp);

        $result = json_decode($response, true);
        if (!is_array($result)) {
            throw new \RuntimeException('CDP 代理返回数据异常');
        }
        if (isset($result['error'])) {
            throw new \RuntimeException('CDP 错误: '.$result['error']);
        }

        return $result['result'] ?? [];
    }

    private function cdpEval(string $expression, bool $returnByValue = true): mixed
    {
        $result = $this->cdpSend('Runtime.evaluate', [
            'expression' => $expression,
            'returnByValue' => $returnByValue,
            'awaitPromise' => true,
        ]);

        return $result['result']['value'] ?? null;
    }

    private function waitForLoad(): void
    {
        for ($i = 0; $i < 30; ++$i) {
            $state = $this->cdpEval('document.readyState', true);
            if ('complete' === $state) {
                return;
            }
            usleep(200000);
        }
    }

    private function screenshotToFile(string $savePath, ?string $selector, bool $fullPage): array
    {
        try {
            $params = ['format' => 'png'];
            if ($fullPage) {
                $metrics = $this->cdpSend('Page.getLayoutMetrics');
                $contentSize = $metrics['cssContentSize'] ?? null;
                if ($contentSize) {
                    $params['captureBeyondViewport'] = true;
                    $params['clip'] = [
                        'x' => 0,
                        'y' => 0,
                        'width' => $contentSize['width'] ?? 1280,
                        'height' => $contentSize['height'] ?? 800,
                        'scale' => 1,
                    ];
                }
            } elseif ($selector) {
                $doc = $this->cdpSend('DOM.getDocument');
                $qs = $this->cdpSend('DOM.querySelector', [
                    'nodeId' => $doc['root']['nodeId'],
                    'selector' => $selector,
                ]);
                $nodeId = $qs['nodeId'] ?? 0;
                if (0 === $nodeId) {
                    return ['error' => "未找到元素: $selector"];
                }
                $box = $this->cdpSend('DOM.getBoxModel', ['nodeId' => $nodeId]);
                $quad = $box['model']['border'] ?? null;
                if (!$quad) {
                    return ['error' => "元素不可见: $selector"];
                }
                $params['clip'] = [
                    'x' => $quad[0],
                    'y' => $quad[1],
                    'width' => max(1, $quad[2] - $quad[0]),
                    'height' => max(1, $quad[5] - $quad[1]),
                    'scale' => 1,
                ];
            }

            $result = $this->cdpSend('Page.captureScreenshot', $params);
            $base64 = $result['data'] ?? '';
            if ('' === $base64) {
                return ['error' => '截图返回为空'];
            }
            $data = base64_decode($base64);
            file_put_contents($savePath, $data);

            return ['path' => $savePath, 'bytes' => strlen($data)];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    // ---------- 自动登录 ----------

    private function login(string $baseUrl, string $username, string $password): bool
    {
        try {
            $this->cdpSend('Page.navigate', ['url' => $baseUrl.'/login']);
            $this->waitForLoad();
            usleep(600000);

            // 读取 CSRF token
            $csrf = $this->cdpEval("document.querySelector('input[name=\"_csrf_token\"]')?.value ?? ''");
            if (!$csrf) {
                $this->writeln('  ! 未找到 CSRF token');

                return false;
            }

            // 填写表单
            $this->cdpEval("document.querySelector('#inputEmail').value = ".json_encode($username).'; true');
            $this->cdpEval("document.querySelector('#inputPassword').value = ".json_encode($password).'; true');

            // 提交表单
            $submitted = $this->cdpEval("(function(){ var f=document.querySelector('form'); if(!f) return false; f.requestSubmit ? f.requestSubmit() : f.submit(); return true; })()");

            // 等待跳转
            for ($i = 0; $i < 20; ++$i) {
                usleep(300000);
                $url = $this->cdpEval('window.location.pathname', true);
                if ('/login' !== $url) {
                    return true;
                }
                if ($i >= 19) {
                    break;
                }
            }

            return false;
        } catch (\Exception $e) {
            $this->writeln('  登录异常: '.$e->getMessage());

            return false;
        }
    }

    private function writeln(string $msg): void
    {
        // 简单的输出辅助（避免注入 IO 对象）
        fwrite(STDOUT, $msg."\n");
    }

    // ---------- 页面清单 ----------

    /**
     * 内置截图页面清单。key = 页面标识；filename = 保存文件名（不含扩展名）。
     * url 必须为真实的 Symfony 路由（可通过 `php bin/console debug:router` 确认）。
     */
    private function targets(): array
    {
        return [
            'login' => [
                'url' => '/login',
                'filename' => 'login',
                'desc' => '登录页',
            ],
            'dashboard' => [
                'url' => '/admin/index',
                'filename' => 'dashboard',
                'desc' => '管理后台首页',
            ],
            'organization' => [
                'url' => '/admin/org/department',
                'filename' => 'organization-tree',
                'desc' => '组织架构 - 部门树',
            ],
            'position' => [
                'url' => '/admin/org/position',
                'filename' => 'organization-position',
                'desc' => '组织架构 - 岗位管理',
            ],
            'position-level' => [
                'url' => '/admin/org/position/level',
                'filename' => 'organization-position-level',
                'desc' => '组织架构 - 职级管理',
            ],
            'employee' => [
                'url' => '/employee/list',
                'filename' => 'employee-roster',
                'desc' => '员工花名册',
            ],
            'entity' => [
                'url' => '/admin/platform/entity/index',
                'filename' => 'dynamic-model-entity-list',
                'desc' => '动态模型 - 实体管理',
            ],
            'view' => [
                'url' => '/admin/platform/view/index',
                'filename' => 'view-designer',
                'desc' => '视图设计器',
            ],
            'datagrid' => [
                'url' => '/admin/platform/view/index',
                'filename' => 'datagrid',
                'desc' => 'DataGrid（视图管理页面）',
            ],
            'menu' => [
                'url' => '/admin/platform/menu/index',
                'filename' => 'menu-management',
                'desc' => '菜单管理',
            ],
            'llm-config' => [
                'url' => '/admin/platform/llm-config',
                'filename' => 'llm-config-list',
                'desc' => 'LLM 配置列表',
            ],
            'scheduler' => [
                'url' => '/admin/task/',
                'filename' => 'scheduler-list',
                'desc' => '定时任务',
            ],
            'calendar' => [
                'url' => '/admin/calendar',
                'filename' => 'system-calendar',
                'desc' => '系统日历',
            ],
            'storage' => [
                'url' => '/admin/storage/',
                'filename' => 'storage-config',
                'desc' => '文件存储配置',
            ],
            'email' => [
                'url' => '/admin/email/',
                'filename' => 'email-config',
                'desc' => '邮件配置',
            ],
            'security' => [
                'url' => '/admin/security/',
                'filename' => 'security-config',
                'desc' => '安全配置',
            ],
            'password-policy' => [
                'url' => '/admin/security/password-policy',
                'filename' => 'password-policy',
                'desc' => '密码策略',
            ],
            'ai-chat' => [
                'url' => '/admin/platform/view/index',
                'filename' => 'ai-assistant-panel',
                'desc' => 'AI 助手面板（视图页右下角）',
            ],

            // ---------- 深层复杂界面（每个模块最完整的操作界面） ----------

            'view-editor' => [
                'url' => '/admin/platform/view/editor/{viewId}',
                'filename' => 'view-editor-canvas',
                'desc' => '视图设计器 - 编辑器画布（完整表单视图）',
            ],
            'view-detail' => [
                'url' => '/admin/platform/view/detail?id={viewId}',
                'filename' => 'view-detail',
                'desc' => '视图设计器 - 视图详情',
            ],
            'entity-itemtable' => [
                'url' => '/admin/platform/entity/itemtable?token={entityToken}',
                'filename' => 'dynamic-model-itemtable',
                'desc' => '动态模型 - 实体数据表格（字段配置）',
            ],
            'employee-edit' => [
                'url' => '/employee/{employeeId}/edit',
                'filename' => 'employee-edit-form',
                'desc' => '员工管理 - 编辑表单（完整字段）',
            ],
            'position-edit' => [
                'url' => '/admin/org/position/edit/{positionId}',
                'filename' => 'position-edit-form',
                'desc' => '岗位管理 - 编辑表单',
            ],
            'dept-edit' => [
                'url' => '/admin/org/department/edit/{deptId}',
                'filename' => 'department-edit-form',
                'desc' => '部门管理 - 编辑表单',
            ],
            'email-editor' => [
                'url' => '/admin/email/template/editor',
                'filename' => 'email-template-editor',
                'desc' => '邮件 - 模板编辑器',
            ],
            'llm-edit' => [
                'url' => '/admin/platform/llm-config/provider/create',
                'filename' => 'llm-provider-form',
                'desc' => 'LLM 配置 - 厂商编辑表单',
            ],
        ];
    }

    /**
     * 将 URL 中的 {viewId} / {employeeId} / {deptId} / {positionId} / {entityToken} 占位符替换为真实数据 ID。
     */
    private function resolvePlaceholders(string $url): string
    {
        $map = $this->idMap();
        foreach ($map as $key => $value) {
            $url = str_replace('{'.$key.'}', (string) $value, $url);
        }

        return $url;
    }

    private ?array $cachedIdMap = null;

    /**
     * 从数据库查询各实体的真实 ID，用于填充 URL 占位符。
     */
    private function idMap(): array
    {
        if (null !== $this->cachedIdMap) {
            return $this->cachedIdMap;
        }
        $conn = $this->entityManager->getConnection();
        $map = [];

        // 视图：优先选内容最丰富的（最近更新的非内置 view）
        try {
            $row = $conn->fetchAssociative(
                'SELECT id FROM platform_view WHERE deleted_at IS NULL AND type = :type ORDER BY updated_at DESC LIMIT 1',
                ['type' => 'view']
            );
            $map['viewId'] = $row['id'] ?? '';
        } catch (\Throwable) {
        }

        // 员工：优先选一个普通员工（非管理员）
        try {
            $row = $conn->fetchAssociative(
                'SELECT id FROM org_employee WHERE deleted_at IS NULL AND username NOT IN (:admins) ORDER BY created_at LIMIT 1',
                ['admins' => ['sys_admin', 'sec_admin', 'auditor']],
                ['admins' => \Doctrine\DBAL\ArrayParameterType::STRING]
            );
            $map['employeeId'] = $row['id'] ?? '';
        } catch (\Throwable) {
        }

        // 部门
        try {
            $row = $conn->fetchAssociative(
                'SELECT id FROM org_department WHERE deleted_at IS NULL ORDER BY lft LIMIT 1'
            );
            $map['deptId'] = $row['id'] ?? '';
        } catch (\Throwable) {
        }

        // 岗位
        try {
            $row = $conn->fetchAssociative(
                'SELECT id FROM org_position WHERE deleted_at IS NULL ORDER BY created_at LIMIT 1'
            );
            $map['positionId'] = $row['id'] ?? '';
        } catch (\Throwable) {
        }

        // 实体 token（用于 itemtable，选部门实体展示字段表格）
        try {
            $row = $conn->fetchAssociative(
                'SELECT token FROM platform_entity WHERE deleted_at IS NULL AND data_table_name = :tbl LIMIT 1',
                ['tbl' => 'org_department']
            );
            $map['entityToken'] = $row['token'] ?? '';
        } catch (\Throwable) {
        }

        $this->cachedIdMap = $map;

        return $map;
    }

    private function listTargets(SymfonyStyle $io): void
    {
        $io->title('内置截图页面清单');
        $rows = [];
        foreach ($this->targets() as $key => $t) {
            $rows[] = [$key, $t['url'], $t['filename'].'.png', $t['desc']];
        }
        $io->table(['标识', 'URL', '保存文件名', '说明'], $rows);
        $io->note('用法: php bin/console ef:docs:screenshot --login --target=login --target=dashboard 或 --all');
    }

    private function defaultSitePath(): string
    {
        $home = getenv('HOME') ?: getenv('USERPROFILE') ?: '';
        foreach ([$home.'/projects/doggyoa-site', $home.'/doggyoa-site', getcwd().'/../doggyoa-site'] as $candidate) {
            if (is_dir($candidate)) {
                return $candidate;
            }
        }

        return $home.'/projects/doggyoa-site';
    }
}
