<?php

namespace App\Controller\Api\Admin\Platform;

use App\Controller\Api\ApiResponse;
use App\Entity\Platform\View;
use App\Entity\Platform\ViewVersion;
use App\Service\Platform\View\VersionNumber;
use App\Service\Platform\View\ViewCheckpointService;
use App\Service\Platform\View\ViewPathResolver;
use App\Service\Platform\View\ViewVersionHistory;
use App\Service\Platform\View\ViewVersionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class ViewVersionApiController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ViewPathResolver $pathResolver,
        private readonly ViewVersionHistory $versionHistory,
        private readonly ViewCheckpointService $checkpointService,
        private readonly ViewVersionService $viewVersionService,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {}

    #[Route(
        '/api/admin/platform/view/{id}/versions',
        name: 'api_platform_view_versions_list',
        methods: ['GET']
    )]
    public function list(string $id): ApiResponse
    {
        $view = $this->em->getRepository(View::class)->find($id);
        if (!$view || $view->getType() !== 'view') {
            return ApiResponse::error('', 404, '视图不存在');
        }

        $this->reconcile($view);

        $versions = [];
        foreach ($view->getVersions() as $vv) {
            if ($vv->getDeletedAt()) {
                continue;
            }
            $dir = $this->pathResolver->versionedDir($view, $vv->getVersion());
            $versions[] = $this->serializeVersion($vv, $dir);
        }

        return ApiResponse::success(json_encode([
            'viewId' => (string) $view->getId(),
            'currentVersion' => $this->pathResolver->currentVersion($view),
            'versions' => $versions,
        ]));
    }

    #[Route(
        '/api/admin/platform/view/{id}/versions',
        name: 'api_platform_view_versions_create',
        methods: ['POST']
    )]
    public function create(string $id, Request $request): ApiResponse
    {
        $view = $this->em->getRepository(View::class)->find($id);
        if (!$view || $view->getType() !== 'view') {
            return ApiResponse::error('', 404, '视图不存在');
        }

        $payload = $request->toArray();
        $fromVersion = (string) ($payload['fromVersion'] ?? '');
        $targetVersion = (string) ($payload['version'] ?? '');
        $label = (string) ($payload['label'] ?? '');

        $this->reconcile($view);

        try {
            $version = $this->viewVersionService->createVersion(
                $view,
                $fromVersion,
                $targetVersion !== '' ? $targetVersion : null,
                $label !== '' ? $label : null,
            );
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error('', 400, $e->getMessage());
        }

        $newDir = $this->pathResolver->versionedDir($view, $version->getVersion());
        return ApiResponse::success(json_encode([
            'version' => $this->serializeVersion($version, $newDir),
        ]));
    }

    #[Route(
        '/api/admin/platform/view/{id}/versions/{version}/rename',
        name: 'api_platform_view_versions_rename',
        methods: ['POST']
    )]
    public function rename(string $id, string $version, Request $request): ApiResponse
    {
        $view = $this->loadView($id);
        if (!$view) {
            return ApiResponse::error('', 404, '视图不存在');
        }

        $label = trim((string) ($request->toArray()['label'] ?? ''));
        if ($label === '') {
            return ApiResponse::error('', 400, '版本名称不能为空');
        }

        foreach ($view->getVersions() as $vv) {
            if ($vv->getVersion() === $version && !$vv->getDeletedAt()) {
                $vv->setLabel($label);
                $this->em->persist($vv);
                $this->em->flush();

                return ApiResponse::success(json_encode([
                    'version' => $version,
                    'label' => $label,
                    'message' => '已重命名',
                ]));
            }
        }

        return ApiResponse::error('', 404, "版本 $version 不存在");
    }

    #[Route(
        '/api/admin/platform/view/{id}/versions/{version}/activate',
        name: 'api_platform_view_versions_activate',
        methods: ['POST']
    )]
    public function activate(string $id, string $version): ApiResponse
    {
        if (!VersionNumber::isValid($version)) {
            return ApiResponse::error('', 400, '版本号格式无效');
        }

        $view = $this->em->getRepository(View::class)->find($id);
        if (!$view || $view->getType() !== 'view') {
            return ApiResponse::error('', 404, '视图不存在');
        }

        $found = null;
        foreach ($view->getVersions() as $vv) {
            if ($vv->getVersion() === $version && !$vv->getDeletedAt()) {
                $found = $vv;
            }
            $vv->setIsCurrent(false);
        }
        if (!$found) {
            return ApiResponse::error('', 404, "版本 $version 不存在");
        }

        $found->setIsCurrent(true);
        $view->setCurrentVersion($version);
        $this->em->persist($view);
        $this->em->flush();

        return ApiResponse::success(json_encode([
            'version' => $version,
            'message' => "已切换到版本 $version",
        ]));
    }

    #[Route(
        '/api/admin/platform/view/{id}/versions/{version}/undo',
        name: 'api_platform_view_versions_undo',
        methods: ['POST']
    )]
    public function undo(string $id, string $version): ApiResponse
    {
        $view = $this->loadView($id);
        if (!$view) {
            return ApiResponse::error('', 404, '视图不存在');
        }

        if ($this->versionHistory->undo($view, $version)) {
            return ApiResponse::success(json_encode(['message' => '已撤销', 'version' => $version]));
        }
        return ApiResponse::error('', 400, '没有可撤销的操作');
    }

    #[Route(
        '/api/admin/platform/view/{id}/versions/{version}/redo',
        name: 'api_platform_view_versions_redo',
        methods: ['POST']
    )]
    public function redo(string $id, string $version): ApiResponse
    {
        $view = $this->loadView($id);
        if (!$view) {
            return ApiResponse::error('', 404, '视图不存在');
        }

        if ($this->versionHistory->redo($view, $version)) {
            return ApiResponse::success(json_encode(['message' => '已重做', 'version' => $version]));
        }
        return ApiResponse::error('', 400, '没有可重做的操作');
    }

    #[Route(
        '/api/admin/platform/view/{id}/versions/{version}/history',
        name: 'api_platform_view_versions_history',
        methods: ['GET']
    )]
    public function history(string $id, string $version): ApiResponse
    {
        $view = $this->loadView($id);
        if (!$view) {
            return ApiResponse::error('', 404, '视图不存在');
        }

        return ApiResponse::success(json_encode([
            'canUndo' => $this->versionHistory->canUndo($view, $version),
            'canRedo' => $this->versionHistory->canRedo($view, $version),
        ]));
    }

    #[Route(
        '/api/admin/platform/view/{id}/versions/{version}/checkpoint',
        name: 'api_platform_view_versions_checkpoint',
        methods: ['POST']
    )]
    public function createCheckpoint(string $id, string $version, Request $request): ApiResponse
    {
        $view = $this->loadView($id);
        if (!$view) {
            return ApiResponse::error('', 404, '视图不存在');
        }

        $payload = $request->toArray();
        $messageId = $payload['message_id'] ?? $payload['messageId'] ?? null;

        $result = $this->checkpointService->createCheckpoint($view, $version, $messageId ? (string) $messageId : null);

        return ApiResponse::success(json_encode([
            'checkpoint' => $result,
        ]));
    }

    #[Route(
        '/api/admin/platform/view/{id}/versions/{version}/checkpoints',
        name: 'api_platform_view_versions_checkpoints_list',
        methods: ['GET']
    )]
    public function listCheckpoints(string $id, string $version): ApiResponse
    {
        $view = $this->loadView($id);
        if (!$view) {
            return ApiResponse::error('', 404, '视图不存在');
        }

        return ApiResponse::success(json_encode([
            'checkpoints' => $this->checkpointService->listCheckpoints($view, $version),
        ]));
    }

    #[Route(
        '/api/admin/platform/view/{id}/versions/{version}/checkpoint/{checkpointId}/restore',
        name: 'api_platform_view_versions_checkpoint_restore',
        methods: ['POST']
    )]
    public function restoreCheckpoint(string $id, string $version, string $checkpointId): ApiResponse
    {
        $view = $this->loadView($id);
        if (!$view) {
            return ApiResponse::error('', 404, '视图不存在');
        }

        if ($this->checkpointService->restoreCheckpoint($view, $version, $checkpointId)) {
            return ApiResponse::success(json_encode(['message' => '已恢复到该消息时的状态']));
        }
        return ApiResponse::error('', 400, '检查点不存在或不可恢复');
    }

    private function loadView(string $id): ?View
    {
        $view = $this->em->getRepository(View::class)->find($id);
        if (!$view || $view->getType() !== 'view') {
            return null;
        }
        return $view;
    }

    private function reconcile(View $view): void
    {
        $fs = new Filesystem();
        $dir = $this->pathResolver->versionedPath($view);
        if ($dir === '') {
            return;
        }
        $abs = $this->pathResolver->baseDir() . $dir;
        if (!is_dir($abs)) {
            return;
        }

        $existing = [];
        foreach ($view->getVersions() as $vv) {
            $existing[] = $vv->getVersion();
        }

        $entries = scandir($abs);
        if ($entries === false) {
            return;
        }
        $added = false;
        foreach ($entries as $entry) {
            if (!VersionNumber::isValid($entry)) {
                continue;
            }
            if (in_array($entry, $existing, true)) {
                continue;
            }
            $version = new ViewVersion();
            $version->setView($view);
            $version->setVersion($entry);
            $version->setLabel('版本 ' . $entry);
            $version->setIsCurrent(false);
            $view->addVersion($version);
            $this->em->persist($version);
            $added = true;
        }
        if ($added) {
            $this->em->flush();
        }
    }

    private function serializeVersion(ViewVersion $vv, string $dir): array
    {
        $name = $vv->getView()->getName();
        $fileCount = 0;
        $exists = is_dir($dir);
        if ($exists) {
            foreach (['design.twig', 'html.twig'] as $ext) {
                if (file_exists($dir . '/' . $name . '.' . $ext)) {
                    $fileCount++;
                }
            }
        }

        return [
            'version' => $vv->getVersion(),
            'label' => $vv->getLabel(),
            'isCurrent' => $vv->isCurrent(),
            'filesExist' => $exists,
            'fileCount' => $fileCount,
            'updatedAt' => $vv->getUpdatedAt() ? $vv->getUpdatedAt()->format('Y-m-d H:i') : null,
        ];
    }
}
