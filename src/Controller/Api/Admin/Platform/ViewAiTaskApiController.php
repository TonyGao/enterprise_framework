<?php

namespace App\Controller\Api\Admin\Platform;

use App\Controller\Api\ApiResponse;
use App\Entity\Platform\AiViewEnhanceTask;
use App\Message\EnhanceViewMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class ViewAiTaskApiController extends AbstractController
{
    #[Route(
        '/api/admin/platform/view/ai-task/{id}',
        name: 'api_platform_view_ai_task_status',
        methods: ['GET']
    )]
    public function status(string $id, EntityManagerInterface $em): ApiResponse
    {
        $task = $em->getRepository(AiViewEnhanceTask::class)->find($id);
        if (!$task) {
            return ApiResponse::error('', 404, 'msg.ai_task.not_found');
        }

        return ApiResponse::success(json_encode([
            'taskId' => (string) $task->getId(),
            'viewId' => $task->getView() ? (string) $task->getView()->getId() : null,
            'status' => $task->getStatus(),
            'progress' => $task->getProgressText(),
            'result' => $task->getResult(),
            'error' => $task->getErrorMessage(),
        ]));
    }

    #[Route(
        '/api/admin/platform/view/ai-task/{id}/retry',
        name: 'api_platform_view_ai_task_retry',
        methods: ['POST']
    )]
    public function retry(string $id, EntityManagerInterface $em, MessageBusInterface $bus): ApiResponse
    {
        $task = $em->getRepository(AiViewEnhanceTask::class)->find($id);
        if (!$task) {
            return ApiResponse::error('', 404, 'msg.ai_task.not_found');
        }

        $task->setStatus('pending');
        $task->setProgressText('等待重新执行…');
        $task->setErrorMessage(null);
        $task->setResult(null);
        $task->setStartedAt(null);
        $task->setFinishedAt(null);
        $em->flush();

        $view = $task->getView();
        if (!$view) {
            return ApiResponse::error('', 400, 'msg.ai_task.view_not_found');
        }

        $bus->dispatch(new EnhanceViewMessage(
            (string) $view->getId(),
            (string) $task->getId(),
            $task->getRequirement(),
        ));

        return ApiResponse::success(json_encode([
            'taskId' => (string) $task->getId(),
            'status' => 'pending',
        ]));
    }
}
