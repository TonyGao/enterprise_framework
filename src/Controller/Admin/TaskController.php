<?php

namespace App\Controller\Admin;

use App\Controller\Api\ApiResponse;
use App\Entity\System\Task;
use App\Entity\System\TaskLog;
use App\Message\RunTaskMessage;
use App\Repository\System\TaskRepository;
use App\Repository\System\TaskLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/task')]
#[IsGranted('ROLE_ADMIN')]
class TaskController extends AbstractController
{
    public function __construct(
        private readonly TaskRepository $taskRepository,
        private readonly TaskLogRepository $taskLogRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    /** 主页（列表+日历双视图） */
    #[Route('/', name: 'admin_task_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/task/index.html.twig');
    }

    /** API：分页任务列表 */
    #[Route('/api/list', name: 'admin_task_api_list', methods: ['GET'])]
    public function apiList(Request $request): ApiResponse
    {
        $page     = max(1, $request->query->getInt('page', 1));
        $pageSize = min(100, max(10, $request->query->getInt('pageSize', 20)));

        $filters = array_filter([
            'enabled'  => $request->query->has('enabled') ? (bool)$request->query->get('enabled') : null,
            'category' => $request->query->get('category'),
            'keyword'  => $request->query->get('keyword'),
        ], fn($v) => $v !== null && $v !== '');

        $result = $this->taskRepository->findPaginated($page, $pageSize, $filters);

        $items = array_map(fn(Task $t) => $this->serializeTask($t), $result['items']);

        return ApiResponse::success(json_encode([
            'total'    => $result['total'],
            'items'    => $items,
            'page'     => $page,
            'pageSize' => $pageSize,
        ]));
    }

    /** API：统计卡片数据 + 分类列表（合并为一个请求） */
    #[Route('/api/stats', name: 'admin_task_api_stats', methods: ['GET'])]
    public function apiStats(): ApiResponse
    {
        // 所有任务（不筛选）
        $all = $this->taskRepository->findAll();
        $total   = count($all);
        $enabled = 0;
        $errors  = 0;
        $categories = [];

        foreach ($all as $task) {
            if ($task->isEnabled()) $enabled++;
            if ($task->getLatestLog()?->getStatus() === 'error') $errors++;
            if ($task->getCategory() && !in_array($task->getCategory(), $categories, true)) {
                $categories[] = $task->getCategory();
            }
        }
        sort($categories);

        // 今日执行次数
        $todayFrom = new \DateTimeImmutable('today 00:00:00');
        $todayTo   = new \DateTimeImmutable('today 23:59:59');
        $todayCount = $this->taskLogRepository->countBetween($todayFrom, $todayTo);

        return ApiResponse::success(json_encode([
            'total'      => $total,
            'enabled'    => $enabled,
            'todayCount' => $todayCount,
            'errors'     => $errors,
            'categories' => $categories,
        ]));
    }

    /** API：日历数据（当月所有任务执行情况） */
    #[Route('/api/calendar', name: 'admin_task_api_calendar', methods: ['GET'])]
    public function apiCalendar(Request $request): ApiResponse
    {
        $fromParam = trim((string) $request->query->get('from', ''));
        $toParam = trim((string) $request->query->get('to', ''));

        if ($fromParam !== '' && $toParam !== '') {
            $from = new \DateTimeImmutable($fromParam);
            $to = new \DateTimeImmutable($toParam);
            $year = (int) $from->format('Y');
            $month = (int) $from->format('m');
        } else {
            $year  = $request->query->getInt('year', (int)date('Y'));
            $month = $request->query->getInt('month', (int)date('m'));
            $from = new \DateTimeImmutable("{$year}-{$month}-01 00:00:00");
            $to   = $from->modify('last day of this month')->setTime(23, 59, 59);
        }

        if ($to < $from) {
            return ApiResponse::error(json_encode([]), 400, '时间范围无效：to 不能早于 from');
        }

        $rawTasks = $this->taskRepository->findEnabledForCalendar();

        // 将 DateTimeImmutable / Uuid 对象序列化为字符串，避免 json_encode 失败
        $tasks = array_map(static function (array $t): array {
            $payload = $t['payload'] ?? null;
            if (is_string($payload)) {
                $payload = json_decode($payload, true) ?? [];
            }
            // 原生 SQL 使用 snake_case 列名
            $nextRunAtRaw = $t['next_run_at'] ?? $t['nextRunAt'] ?? null;
            $lastRunAtRaw = $t['last_run_at'] ?? $t['lastRunAt'] ?? null;
            return [
                'id'             => (string) ($t['id'] ?? ''),
                'name'           => $t['name'] ?? '',
                'category'       => $t['category'] ?? null,
                'cronExpression' => $t['cron_expression'] ?? $t['cronExpression'] ?? '',
                'payload'        => $payload,
                'nextRunAt'      => $nextRunAtRaw instanceof \DateTimeInterface
                                    ? $nextRunAtRaw->format('Y-m-d H:i:s')
                                    : (is_string($nextRunAtRaw) ? $nextRunAtRaw : null),
                'lastRunAt'      => $lastRunAtRaw instanceof \DateTimeInterface
                                    ? $lastRunAtRaw->format('Y-m-d H:i:s')
                                    : (is_string($lastRunAtRaw) ? $lastRunAtRaw : null),
            ];
        }, $rawTasks);
        $stats = $this->taskLogRepository->getCalendarStats($from, $to);
        $events = $this->buildTaskScheduleEvents($tasks, $from, $to);

        // 以日期为键聚合日志统计
        $statMap = [];
        foreach ($stats as $row) {
            $key = $row['taskId'] . '_' . $row['date'];
            $statMap[$key][$row['status']] = (int)$row['cnt'];
        }

        return ApiResponse::success(json_encode([
            'year'  => $year,
            'month' => $month,
            'from'  => $from->format('Y-m-d H:i:s'),
            'to'    => $to->format('Y-m-d H:i:s'),
            'tasks' => $tasks,
            'stats' => $statMap,
            'events' => $events,
        ]));
    }

    /**
     * 在指定区间内推导每个任务每日的首个计划执行点，避免高频任务导致前端日历过载
     */
private function buildTaskScheduleEvents(array $tasks, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $events = [];

        foreach ($tasks as $task) {
            $taskId = (string) ($task['id'] ?? '');
            if ($taskId === '') {
                continue;
            }

            $nextRunRaw = $task['nextRunAt'] ?? null;
            $lastRunRaw = $task['lastRunAt'] ?? null;
            $cronExpr = (string) ($task['cronExpression'] ?? '');

            // 收集所有需要显示的事件时间点
            $eventDates = [];

            // 转换日期（可能是 DateTimeInterface 或字符串）
            $parseDate = function ($v) {
                if ($v instanceof \DateTimeInterface) {
                    return \DateTimeImmutable::createFromInterface($v);
                }
                if (is_string($v)) {
                    return new \DateTimeImmutable($v);
                }
                return null;
            };

            // 收集所有需要显示的事件时间点（去重）
            $eventDates = [];

            $nextRunAt = $parseDate($nextRunRaw);
            $lastRunAt = $parseDate($lastRunRaw);

            // 检查是否是单次任务
            $payload = $task['payload'] ?? [];
            $isOnce = ($payload['once'] ?? false) === true;

            // 如果是单次任务，只需要显示 nextRunAt，不需要用 cron 展开
            if ($isOnce) {
                $daySeen = [];
                $added = 0;
                $maxPerTask = 5;

                // 只添加 nextRunAt（单次任务执行后 nextRunAt 保持不变）
                if ($nextRunAt) {
                    $this->addSafeEventDate($events, $task, $nextRunAt, $from, $to, $daySeen, $added, $maxPerTask);
                }

                // 如果 lastRunAt 存在且与 nextRunAt 是不同天，也添加（用于显示已执行记录）
                if ($lastRunAt) {
                    $lastKey = $lastRunAt->format('Y-m-d');
                    if (!isset($daySeen[$lastKey])) {
                        $this->addSafeEventDate($events, $task, $lastRunAt, $from, $to, $daySeen, $added, $maxPerTask);
                    }
                }

                continue;
            }

            // 收集所有需要显示的事件时间点（去重）
            $eventDates = [];

            // 如果 nextRunAt 在范围内，添加到事件列表
            if ($nextRunAt) {
                $eventDates[$nextRunAt->format('Y-m-d H:i')] = $nextRunAt;
            }

            // 如果是最近执行过的任务，也显示 lastRunAt（但避免与 nextRunAt 重复）
            // 使用分钟级别去重，避免精度不同导致的重复
            if ($lastRunAt && $lastRunAt >= $from) {
                $key = $lastRunAt->format('Y-m-d H:i');
                // 只有当 lastRunAt 和 nextRunAt 在分钟级别相同时不添加
                if (!isset($eventDates[$key])) {
                    $eventDates[$key] = $lastRunAt;
                }
            }

            if (empty($eventDates)) {
                continue;
            }

            $daySeen = [];
            $added = 0;
            $maxPerTask = 45;

            // 先把 nextRunAt 落点纳入候选，保证单次任务也可显示。
            foreach ($eventDates as $cursor) {
                $this->addSafeEventDate($events, $task, $cursor, $from, $to, $daySeen, $added, $maxPerTask);
            }

            // 单次任务不继续用 cron 循环展开，避免产生多个重复日期
            if ($isOnce || $cronExpr === '' || str_starts_with($cronExpr, '@')) {
                continue;
            }

            $cronClass = 'Cron\\CronExpression';
            if (!class_exists($cronClass)) {
                continue;
            }

            try {
                $cron = new $cronClass($cronExpr);
            } catch (\Throwable) {
                continue;
            }

            // 从 max(nextRunAt, from) 开始向后推导，按"每天只取首个执行点"收敛数量。
            $firstDate = reset($eventDates);
            $seed = $firstDate > $from ? $firstDate : $from;

            for ($i = 0; $i < 500 && $added < $maxPerTask; $i++) {
                $next = \DateTimeImmutable::createFromMutable($cron->getNextRunDate($seed, 0, false));
                if ($next > $to) {
                    break;
                }

                $this->addSafeEventDate($events, $task, $next, $from, $to, $daySeen, $added, $maxPerTask);
                $seed = $next->modify('+1 second');
            }
        }

        return $events;
    }

    private function addSafeEventDate(
        array &$events,
        array $task,
        \DateTimeImmutable $at,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
        array &$daySeen,
        int &$added,
        int $maxPerTask,
    ): void {
        if ($at < $from || $at > $to || $added >= $maxPerTask) {
            return;
        }

        $dateKey = $at->format('Y-m-d');
        if (isset($daySeen[$dateKey])) {
            return;
        }

        $events[] = [
            'taskId' => (string) ($task['id'] ?? ''),
            'name' => (string) ($task['name'] ?? ''),
            'category' => $task['category'] ?? null,
            'date' => $dateKey,
            'time' => $at->format('H:i:s'),
            'status' => 'pending',
        ];

        $daySeen[$dateKey] = true;
        $added++;
    }

    /** API：创建任务 */
    #[Route('/api/create', name: 'admin_task_api_create', methods: ['POST'])]
    public function apiCreate(Request $request): ApiResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$this->isValidTaskData($data)) {
            return ApiResponse::error(json_encode([]), 400, '参数不完整');
        }

        $task = new Task();
        $this->fillTask($task, $data);

        $this->em->persist($task);
        $this->em->flush();

        return ApiResponse::success(json_encode(['task' => $this->serializeTask($task)]), 201, '任务创建成功');
    }

    /** API：更新任务 */
    #[Route('/api/{id}/update', name: 'admin_task_api_update', methods: ['PUT', 'PATCH'])]
    public function apiUpdate(Request $request, Task $id): ApiResponse
    {
        $data = json_decode($request->getContent(), true);
        $this->fillTask($id, $data);
        $this->em->flush();

        return ApiResponse::success(json_encode(['task' => $this->serializeTask($id)]));
    }

    /** API：删除任务 */
    #[Route('/api/{id}/delete', name: 'admin_task_api_delete', methods: ['DELETE'])]
    public function apiDelete(Task $id): ApiResponse
    {
        $this->em->remove($id);
        $this->em->flush();

        return ApiResponse::success(json_encode([]), 200, '任务已删除');
    }

    /** API：启用/禁用任务 */
    #[Route('/api/{id}/toggle', name: 'admin_task_api_toggle', methods: ['POST'])]
    public function apiToggle(Task $id): ApiResponse
    {
        $id->setEnabled(!$id->isEnabled());
        $this->em->flush();

        return ApiResponse::success(json_encode(['enabled' => $id->isEnabled()]));
    }

    /** API：立即手动执行任务 */
    #[Route('/api/{id}/run-now', name: 'admin_task_api_run_now', methods: ['POST'])]
    public function apiRunNow(Task $id, MessageBusInterface $bus): ApiResponse
    {
        $bus->dispatch(new RunTaskMessage((string)$id->getId()));

        return ApiResponse::success(json_encode([]), 200, "任务「{$id->getName()}」已投递，即将异步执行");
    }

    /** API：查看任务执行日志 */
    #[Route('/api/{id}/logs', name: 'admin_task_api_logs', methods: ['GET'])]
    public function apiLogs(Request $request, Task $id): ApiResponse
    {
        $page     = max(1, $request->query->getInt('page', 1));
        $pageSize = min(100, max(10, $request->query->getInt('pageSize', 20)));
        $displayTz = new \DateTimeZone('Asia/Shanghai');

        $result = $this->taskLogRepository->findByTaskPaginated($id, $page, $pageSize);

        $items = array_map(fn(TaskLog $l) => [
            'id'          => (string)$l->getId(),
            'status'      => $l->getStatus(),
            'output'      => $l->getOutput(),
            'executionMs' => $l->getExecutionMs(),
            'memoryPeak'  => $l->getMemoryPeakFormatted(),
            'hostName'    => $l->getHostName(),
            'startedAt'   => $l->getStartedAt()->setTimezone($displayTz)->format('Y-m-d H:i:s'),
            'finishedAt'  => $l->getFinishedAt()?->setTimezone($displayTz)->format('Y-m-d H:i:s'),
        ], $result['items']);

        return ApiResponse::success(json_encode([
            'total'    => $result['total'],
            'items'    => $items,
            'taskName' => $id->getName(),
        ]));
    }

    /** API：获取所有任务分类（保留兼容，推荐使用 /api/stats） */
    #[Route('/api/categories', name: 'admin_task_api_categories', methods: ['GET'])]
    public function apiCategories(): ApiResponse
    {
        $categories = $this->em->createQuery(
            'SELECT DISTINCT t.category FROM App\Entity\System\Task t WHERE t.category IS NOT NULL ORDER BY t.category ASC'
        )->getScalarResult();

        return ApiResponse::success(json_encode(array_column($categories, 'category')));
    }

    /** API：AI 自然语言解析调度规则 */
    #[Route('/api/parse-schedule', name: 'admin_task_api_parse_schedule', methods: ['POST'])]
    public function apiParseSchedule(Request $request): ApiResponse
    {
        $data = json_decode($request->getContent(), true);
        $text = trim($data['text'] ?? '');

        if (empty($text)) {
            return ApiResponse::error(json_encode(['message' => '请输入调度规则描述']), 400, 'task.error.missing_text');
        }

        $result = $this->parseScheduleText($text);

        if ($result === null) {
            return ApiResponse::error(json_encode(['message' => '无法识别此调度描述，请尝试更明确的表达，如"每天上午9点"、"每隔5分钟"、"每周一下午3点"']), 422, 'task.error.parse_failed');
        }

        return ApiResponse::success(json_encode($result));
    }

    /**
     * 规则式自然语言 → Cron 表达式解析
     * 支持常见中文调度描述
     */
    private function parseScheduleText(string $text): ?array
    {
        $text = mb_strtolower(trim($text));

        // 时间提取辅助
        $timeMap = [
            '凌晨' => 0, '早上' => 8, '上午' => 9, '中午' => 12,
            '下午' => 14, '傍晚' => 18, '晚上' => 20, '深夜' => 23,
        ];
        $weekMap = [
            '一' => 1, '二' => 2, '三' => 3, '四' => 4, '五' => 5, '六' => 6, '天' => 0, '日' => 0,
        ];

        // 提取小时分钟，如"3点30分"、"15:30"、"9点"
        $extractTime = function(string $s) use ($timeMap): array {
            $hour = 0; $min = 0;
            // HH:MM 格式
            if (preg_match('/(\d{1,2}):(\d{2})/', $s, $m)) {
                return [(int)$m[1], (int)$m[2]];
            }
            // N点M分
            if (preg_match('/(\d{1,2})点(\d{1,2})分?/', $s, $m)) {
                return [(int)$m[1], (int)$m[2]];
            }
            // N点
            if (preg_match('/(\d{1,2})点/', $s, $m)) {
                return [(int)$m[1], 0];
            }
            // 时段词 + N点
            foreach ($timeMap as $word => $base) {
                if (str_contains($s, $word)) {
                    if (preg_match('/(\d{1,2})点(\d{1,2})分?/', $s, $m)) {
                        $h = (int)$m[1] < 12 ? (int)$m[1] + $base - 8 : (int)$m[1];
                        return [min(23, max(0, $h)), (int)($m[2] ?? 0)];
                    }
                    if (preg_match('/(\d{1,2})点/', $s, $m)) {
                        $h = (int)$m[1] < 12 ? (int)$m[1] + $base - 8 : (int)$m[1];
                        return [min(23, max(0, $h)), 0];
                    }
                    return [$base, 0];
                }
            }
            return [0, 0];
        };

        // ── 每隔N秒 ──
        if (preg_match('/每[隔过]?(\d+)\s*秒/', $text, $m)) {
            $n = (int)$m[1];
            return ['cronExpression' => "@seconds:{$n}", 'human' => "每隔 {$n} 秒"];
        }

        // ── 每秒 ──
        if (str_contains($text, '每秒')) {
            return ['cronExpression' => '@seconds:1', 'human' => '每隔 1 秒'];
        }

        // ── 每隔N分钟 ──
        if (preg_match('/每[隔过]?(\d+)\s*分钟?/', $text, $m)) {
            $n = min(59, max(1, (int)$m[1]));
            return ['cronExpression' => "*/{$n} * * * *", 'human' => "每隔 {$n} 分钟"];
        }

        // ── 每分钟 ──
        if (str_contains($text, '每分钟') || str_contains($text, '每隔一分钟')) {
            return ['cronExpression' => '*/1 * * * *', 'human' => '每隔 1 分钟'];
        }

        // ── 每隔N小时 ──
        if (preg_match('/每[隔过]?(\d+)\s*小时/', $text, $m)) {
            $n = min(23, max(1, (int)$m[1]));
            [$h, $mm] = $extractTime($text);
            $mmStr = $mm > 0 ? ":{$mm}" : '';
            return ['cronExpression' => "{$mm} */{$n} * * *", 'human' => "每隔 {$n} 小时{$mmStr}"];
        }

        // ── 每小时 ──
        if (str_contains($text, '每小时') || str_contains($text, '每个小时')) {
            [$h, $mm] = $extractTime($text);
            return ['cronExpression' => "{$mm} * * * *", 'human' => "每小时 :".str_pad($mm, 2, '0', STR_PAD_LEFT)];
        }

        // ── 每隔N天 ──
        if (preg_match('/每[隔过]?(\d+)\s*天/', $text, $m)) {
            $n = max(1, (int)$m[1]);
            [$h, $mm] = $extractTime($text);
            $expr = $n === 1 ? "{$mm} {$h} * * *" : "{$mm} {$h} */{$n} * *";
            return ['cronExpression' => $expr, 'human' => "每隔 {$n} 天，".str_pad($h, 2, '0', STR_PAD_LEFT).":".str_pad($mm, 2, '0', STR_PAD_LEFT)];
        }

        // ── 每天 ──
        if (str_contains($text, '每天') || str_contains($text, '每日')) {
            [$h, $mm] = $extractTime($text);
            return ['cronExpression' => "{$mm} {$h} * * *", 'human' => "每天 ".str_pad($h, 2, '0', STR_PAD_LEFT).":".str_pad($mm, 2, '0', STR_PAD_LEFT)];
        }

        // ── 每周X（多天支持） ──
        if (preg_match('/每周([一二三四五六天日]+)/', $text, $m) || preg_match('/每个?星期([一二三四五六天日]+)/', $text, $m)) {
            $dayStr = $m[1];
            $days = [];
            foreach ($weekMap as $ch => $num) {
                if (mb_strpos($dayStr, $ch) !== false) {
                    $days[] = $num;
                }
            }
            if (empty($days)) $days = [1];
            sort($days);
            [$h, $mm] = $extractTime($text);
            $dowStr = implode(',', $days);
            return ['cronExpression' => "{$mm} {$h} * * {$dowStr}", 'human' => "每周 ".implode('、', array_map(fn($d) => ['日','一','二','三','四','五','六'][$d], $days))."，".str_pad($h, 2, '0', STR_PAD_LEFT).":".str_pad($mm, 2, '0', STR_PAD_LEFT)];
        }

        // ── 每月最后一天 / 月末 ──
        if (str_contains($text, '月末') || str_contains($text, '每月最后') || str_contains($text, '月底')) {
            [$h, $mm] = $extractTime($text);
            return ['cronExpression' => "@monthly-last:1:{$h}:{$mm}", 'human' => "每月月末，".str_pad($h, 2, '0', STR_PAD_LEFT).":".str_pad($mm, 2, '0', STR_PAD_LEFT)];
        }

        // ── 每月第N天 / 每月N号 ──
        if (preg_match('/每月[第]?(\d+)[日号天]/', $text, $m)) {
            $day = min(31, max(1, (int)$m[1]));
            [$h, $mm] = $extractTime($text);
            return ['cronExpression' => "{$mm} {$h} {$day} * *", 'human' => "每月第 {$day} 天，".str_pad($h, 2, '0', STR_PAD_LEFT).":".str_pad($mm, 2, '0', STR_PAD_LEFT)];
        }

        // ── 每月 月初 ──
        if (str_contains($text, '月初') || (str_contains($text, '每月') && str_contains($text, '1日'))) {
            [$h, $mm] = $extractTime($text);
            return ['cronExpression' => "{$mm} {$h} 1 * *", 'human' => "每月1日，".str_pad($h, 2, '0', STR_PAD_LEFT).":".str_pad($mm, 2, '0', STR_PAD_LEFT)];
        }

        // ── 每季度 ──
        if (str_contains($text, '季度') || str_contains($text, '每季')) {
            $mon = 1; $day = 1;
            if (preg_match('/第(\d)月/', $text, $m)) $mon = min(3, max(1, (int)$m[1]));
            if (preg_match('/第(\d+)[日天号]/', $text, $m)) $day = min(31, max(1, (int)$m[1]));
            [$h, $mm] = $extractTime($text);
            return ['cronExpression' => "@quarterly:1:{$mon}:{$day}:{$h}:{$mm}", 'human' => "每季度，第 {$mon} 月第 {$day} 天，".str_pad($h, 2, '0', STR_PAD_LEFT).":".str_pad($mm, 2, '0', STR_PAD_LEFT)];
        }

        return null;
    }

    // -------------------------------------------------------------------------

    private function serializeTask(Task $t): array
    {
        $latestLog = $t->getLatestLog();
        return [
            'id'            => (string)$t->getId(),
            'name'          => $t->getName(),
            'description'   => $t->getDescription(),
            'cronExpression'=> $t->getCronExpression(),
            'handler'       => $t->getHandler(),
            'payload'       => $t->getPayload(),
            'enabled'       => $t->isEnabled(),
            'category'      => $t->getCategory(),
            'lastRunAt'     => $t->getLastRunAt()?->format('Y-m-d H:i:s'),
            'nextRunAt'     => $t->getNextRunAt()->format('Y-m-d H:i:s'),
            'maxRetries'    => $t->getMaxRetries(),
            'timeout'       => $t->getTimeout(),
            'sortOrder'     => $t->getSortOrder(),
            'createdAt'     => $t->getCreatedAt()->format('Y-m-d H:i:s'),
            'latestStatus'  => $latestLog?->getStatus(),
        ];
    }

    private function fillTask(Task $task, array $data): void
    {
        $displayTz = new \DateTimeZone('Asia/Shanghai');

        if (isset($data['name']))           $task->setName(trim($data['name']));
        if (isset($data['description']))    $task->setDescription($data['description'] ?: null);
        if (isset($data['cronExpression'])) $task->setCronExpression(trim($data['cronExpression']));
        if (isset($data['handler']))        $task->setHandler(trim($data['handler']));
        if (isset($data['payload']))        $task->setPayload((array)$data['payload']);
        if (isset($data['enabled']))        $task->setEnabled((bool)$data['enabled']);
        if (isset($data['category']))       $task->setCategory($data['category'] ?: null);
        if (isset($data['maxRetries']))     $task->setMaxRetries((int)$data['maxRetries']);
        if (isset($data['timeout']))        $task->setTimeout((int)$data['timeout']);
        if (isset($data['sortOrder']))      $task->setSortOrder((int)$data['sortOrder']);
        if (isset($data['nextRunAt'])) {
            $task->setNextRunAt(new \DateTimeImmutable($data['nextRunAt'], $displayTz));

            // 单次任务修改执行时间时，自动重新启用，避免因上次执行后被禁用而不再触发。
            $payload = $task->getPayload();
            if (($payload['once'] ?? false) === true) {
                $task->setEnabled(true);
            }
        }
    }

    private function isValidTaskData(array $data): bool
    {
        return !empty($data['name'])
            && !empty($data['cronExpression'])
            && !empty($data['handler']);
    }
}
