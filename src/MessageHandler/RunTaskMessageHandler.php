<?php

namespace App\MessageHandler;

use App\Entity\System\Task;
use App\Entity\System\TaskLog;
use App\Message\RunTaskMessage;
use App\Repository\System\TaskRepository;
use App\Service\Task\TaskHandlerLocator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class RunTaskMessageHandler
{
    public function __construct(
        private readonly TaskRepository $taskRepository,
        private readonly TaskHandlerLocator $taskLocator,
        private readonly EntityManagerInterface $em,
    ) {}

    public function __invoke(RunTaskMessage $message): void
    {
        $task = $this->taskRepository->find($message->taskId);

        if (!$task instanceof Task || !$task->isEnabled()) {
            return; // 任务被删除或临时禁用，静默跳过
        }

        // 创建执行日志
        $log = new TaskLog();
        $log->setTask($task)
            ->setStatus('running')
            ->setStartedAt(new \DateTimeImmutable())
            ->setHostName((string)gethostname());

        $this->em->persist($log);
        $this->em->flush();

        $startTime = microtime(true);
        $memBefore = memory_get_usage(true);

        try {
            $runner = $this->taskLocator->get($task->getHandler());

            ob_start();
            $runner->handle($task->getPayload());
            $output = ob_get_clean() ?: null;

            $log->setStatus('success')
                ->setOutput($output)
                ->setExecutionMs((int)((microtime(true) - $startTime) * 1000))
                ->setMemoryPeak(memory_get_usage(true) - $memBefore)
                ->setFinishedAt(new \DateTimeImmutable());
        } catch (\Throwable $e) {
            $log->setStatus('error')
                ->setOutput($e->getMessage() . "\n" . $e->getTraceAsString())
                ->setExecutionMs((int)((microtime(true) - $startTime) * 1000))
                ->setFinishedAt(new \DateTimeImmutable());

            $this->em->flush();
            throw $e; // 重新抛出以触发 Messenger retry_strategy
        }

        $this->em->flush();
    }
}
