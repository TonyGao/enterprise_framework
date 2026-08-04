<?php

namespace App\Service\Platform\View;

use App\Entity\Platform\AiChatCheckpoint;
use App\Entity\Platform\View;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Uid\Uuid;

/**
 * AI 对话消息级视图检查点：把某条 AI 回复后的视图版本文件快照存下来，
 * 供前端在对应消息上提供「回退到此」，恢复该消息时的视图状态。
 * 通过内容 hash 去重：只有真正改变视图的回复才产生新检查点。
 */
class ViewCheckpointService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ViewPathResolver $pathResolver,
    ) {}

    /**
     * 为当前视图版本创建检查点（若内容与上一个检查点相同则跳过）。
     * @return array|null {id, messageId}
     */
    public function createCheckpoint(View $view, string $version, ?string $messageId = null): ?array
    {
        $dir = $this->versionedDir($view, $version);
        if (!is_dir($dir)) {
            return null;
        }

        $name = $view->getName();
        $design = $dir . '/' . $name . '.design.twig';
        $html = $dir . '/' . $name . '.html.twig';
        if (!file_exists($design) && !file_exists($html)) {
            return null;
        }

        $hash = $this->hashFiles($design, $html);

        // 与上一个检查点内容相同则跳过
        $last = $this->lastCheckpoint($view, $version);
        if ($last && $last->getContentHash() === $hash) {
            return ['id' => (string) $last->getId(), 'messageId' => $messageId, 'created' => false];
        }

        $checkpoint = new AiChatCheckpoint();
        $checkpoint->setViewId($view->getId());
        $checkpoint->setVersion($version);
        if ($messageId) {
            $checkpoint->setMessageId(Uuid::fromString($messageId));
        }
        $checkpoint->setContentHash($hash);

        $fs = new Filesystem();
        $snapDir = '.checkpoints/' . $checkpoint->getId();
        $absSnap = $dir . '/' . $snapDir;
        $fs->mkdir($absSnap, 0755);
        if (file_exists($design)) {
            $fs->copy($design, $absSnap . '/' . $name . '.design.twig');
        }
        if (file_exists($html)) {
            $fs->copy($html, $absSnap . '/' . $name . '.html.twig');
        }
        $checkpoint->setSnapshotDir($snapDir);

        $this->em->persist($checkpoint);
        $this->em->flush();

        return ['id' => (string) $checkpoint->getId(), 'messageId' => $messageId, 'created' => true];
    }

    /**
     * 恢复到指定检查点。
     */
    public function restoreCheckpoint(View $view, string $version, string $checkpointId): bool
    {
        $checkpoint = $this->em->getRepository(AiChatCheckpoint::class)->find($checkpointId);
        if (!$checkpoint || (string) $checkpoint->getViewId() !== (string) $view->getId() || $checkpoint->getVersion() !== $version) {
            return false;
        }

        $dir = $this->versionedDir($view, $version);
        $snapDir = $dir . '/' . $checkpoint->getSnapshotDir();
        if (!is_dir($snapDir)) {
            return false;
        }

        $name = $view->getName();
        $fs = new Filesystem();
        foreach (['design.twig', 'html.twig'] as $ext) {
            $src = $snapDir . '/' . $name . '.' . $ext;
            $dst = $dir . '/' . $name . '.' . $ext;
            if (file_exists($src)) {
                $fs->copy($src, $dst, true);
            }
        }

        return true;
    }

    /**
     * @return array<int, array{id: string, messageId: string|null, createdAt: string|null}>
     */
    public function listCheckpoints(View $view, string $version): array
    {
        $repo = $this->em->getRepository(AiChatCheckpoint::class);
        $rows = $repo->findBy(
            ['viewId' => $view->getId(), 'version' => $version],
            ['orderNum' => 'ASC']
        );

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id' => (string) $row->getId(),
                'messageId' => $row->getMessageId() ? (string) $row->getMessageId() : null,
                'createdAt' => $row->getCreatedAt()?->format('Y-m-d H:i'),
            ];
        }
        return $out;
    }

    private function versionedDir(View $view, string $version): string
    {
        return $this->pathResolver->versionedDir($view, $version);
    }

    private function hashFiles(string ...$files): string
    {
        $parts = [];
        foreach ($files as $f) {
            $parts[] = file_exists($f) ? (string) file_get_contents($f) : '';
        }
        return md5(implode('|', $parts));
    }

    private function lastCheckpoint(View $view, string $version): ?AiChatCheckpoint
    {
        $repo = $this->em->getRepository(AiChatCheckpoint::class);
        // orderNum 逐条自增（CommonTrait），比 createdAt 秒级精度可靠
        return $repo->findOneBy(
            ['viewId' => $view->getId(), 'version' => $version],
            ['orderNum' => 'DESC']
        );
    }
}
