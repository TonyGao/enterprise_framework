<?php

namespace App\MessageHandler;

use App\Entity\Platform\AiViewEnhanceTask;
use App\Entity\Platform\View;
use App\Message\EnhanceViewMessage;
use App\Service\AI\Orchestrator\ViewEnhanceAgentOrchestrator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class EnhanceViewMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ViewEnhanceAgentOrchestrator $orchestrator,
        private readonly HubInterface $hub,
    ) {}

    public function __invoke(EnhanceViewMessage $message): void
    {
        $task = $this->em->getRepository(AiViewEnhanceTask::class)->find($message->taskId);
        $view = $this->em->getRepository(View::class)->find($message->viewId);

        if (!$task) {
            return;
        }
        if (!$view) {
            $this->fail($task, '视图不存在，无法进行 AI 加工');
            return;
        }

        $task->setStatus('running');
        $task->setStartedAt(new \DateTime());
        $task->setProgressText('AI 正在处理…');
        $task->setErrorMessage(null);
        $this->em->flush();

        $progress = function (string $text) use ($task): void {
            $task->setProgressText($text);
            $this->em->flush();
            $this->publish($task, $text);
        };

        try {
            $result = $this->orchestrator->run($view, $message->requirement, $progress);

            $task->setStatus('success');
            $task->setProgressText('AI 加工完成');
            $task->setResult(mb_substr($result['reply'] ?? '', 0, 2000));
            $task->setFinishedAt(new \DateTime());
            $this->em->flush();
            $this->publish($task, 'AI 加工完成');
        } catch (\Throwable $e) {
            $this->fail($task, 'AI 加工失败: ' . $e->getMessage());
        }
    }

    private function fail(AiViewEnhanceTask $task, string $message): void
    {
        $task->setStatus('error');
        $task->setProgressText('AI 加工失败');
        $task->setErrorMessage($message);
        $task->setFinishedAt(new \DateTime());
        $this->em->flush();
        $this->publish($task, $message);
    }

    private function publish(AiViewEnhanceTask $task, string $progress): void
    {
        try {
            $update = new Update(
                'https://enterprise.local/ai/view-task/' . $task->getId(),
                json_encode([
                    'type' => 'view_ai_task_update',
                    'taskId' => (string) $task->getId(),
                    'viewId' => $task->getView() ? (string) $task->getView()->getId() : null,
                    'status' => $task->getStatus(),
                    'progress' => $progress,
                    'error' => $task->getErrorMessage(),
                    'result' => $task->getResult(),
                ]),
                true
            );
            $this->hub->publish($update);
        } catch (\Throwable $e) {
            // Mercure 推送失败不影响任务状态落库
        }
    }
}
