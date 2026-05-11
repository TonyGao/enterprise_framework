<?php

namespace App\Controller;

use App\Service\Task\TaskScheduler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * 内部定时任务触发入口（仅供 Crontab / K8s CronJob 调用）
 *
 * 安全策略：
 *   1. 建议配置 Caddy/Nginx IP 白名单，仅允许 127.0.0.1 访问此路由
 *   2. 或在此处校验 X-Scheduler-Token Header
 */
#[Route('/internal')]
class SchedulerController extends AbstractController
{
    #[Route('/scheduler/run', name: 'internal_scheduler_run', methods: ['GET', 'POST'])]
    public function run(Request $request, TaskScheduler $scheduler): JsonResponse
    {
        // 简单 IP 检查：仅允许本机请求（生产环境应在网络层限制）
        $clientIp = $request->getClientIp();
        if (!in_array($clientIp, ['127.0.0.1', '::1', null], true)) {
            // 允许通过 X-Scheduler-Token 覆盖
            $token = $request->headers->get('X-Scheduler-Token');
            $expectedToken = $_ENV['SCHEDULER_TOKEN'] ?? null;
            if (!$expectedToken || $token !== $expectedToken) {
                return $this->json(['error' => 'Forbidden'], 403);
            }
        }

        $dispatched = $scheduler->run();

        return $this->json([
            'status'     => 'ok',
            'dispatched' => $dispatched,
            'at'         => date('Y-m-d H:i:s'),
        ]);
    }
}
