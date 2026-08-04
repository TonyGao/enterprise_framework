<?php

namespace App\Service\Platform\View;

use App\Entity\Platform\View;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

/**
 * 视图版本文件级回滚（撤销/重做）。
 *
 * 机制：每次保存前，将当前版本的 design/html 文件快照进 undo 栈；
 * undo 恢复上一个快照并把当前状态压入 redo 栈；redo 反向操作。
 * 快照以文件形式存放在 {versionDir}/.history/undo|redo/ 下。
 * 与「视图内容以文件为事实来源」一致，供 AI 命令回退与工具栏撤销/重做共用。
 */
class ViewVersionHistory
{
    private const MAX_SNAPSHOTS = 20;

    public function __construct(
        private readonly ViewPathResolver $pathResolver,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {}

    /**
     * 保存前调用：把当前文件快照入 undo 栈，并清空 redo 栈。
     */
    public function snapshotOnSave(View $view, string $version): void
    {
        $dir = $this->versionedDir($view, $version);
        if (!is_dir($dir)) {
            return;
        }

        $name = $view->getName();
        $design = $dir . '/' . $name . '.design.twig';
        $html = $dir . '/' . $name . '.html.twig';
        if (!file_exists($design) && !file_exists($html)) {
            return;
        }

        $fs = new Filesystem();
        $snapDir = $dir . '/.history/undo/' . $this->newSnapshotId();
        $fs->mkdir($snapDir, 0755);
        if (file_exists($design)) {
            $fs->copy($design, $snapDir . '/' . $name . '.design.twig');
        }
        if (file_exists($html)) {
            $fs->copy($html, $snapDir . '/' . $name . '.html.twig');
        }

        $this->trimStack($dir . '/.history/undo');
        $this->clearStack($dir . '/.history/redo');
    }

    /**
     * 撤销：恢复上一个快照。返回被撤销前是否有可恢复内容。
     */
    public function undo(View $view, string $version): bool
    {
        $dir = $this->versionedDir($view, $version);
        $undoDir = $dir . '/.history/undo';
        $redoDir = $dir . '/.history/redo';

        $snap = $this->latestSnapshot($undoDir);
        if (!$snap) {
            return false;
        }

        // 当前状态压入 redo 栈
        $this->pushCurrentTo($view, $version, $redoDir);
        // 恢复快照到版本目录
        $this->restoreSnapshot($view, $version, $snap);
        // 移除已使用的快照
        $this->removeSnapshot($snap);

        return true;
    }

    /**
     * 重做：恢复被撤销的状态。返回是否有可重做内容。
     */
    public function redo(View $view, string $version): bool
    {
        $dir = $this->versionedDir($view, $version);
        $undoDir = $dir . '/.history/undo';
        $redoDir = $dir . '/.history/redo';

        $snap = $this->latestSnapshot($redoDir);
        if (!$snap) {
            return false;
        }

        $this->pushCurrentTo($view, $version, $undoDir);
        $this->restoreSnapshot($view, $version, $snap);
        $this->removeSnapshot($snap);

        return true;
    }

    public function canUndo(View $view, string $version): bool
    {
        return $this->latestSnapshot($this->versionedDir($view, $version) . '/.history/undo') !== null;
    }

    public function canRedo(View $view, string $version): bool
    {
        return $this->latestSnapshot($this->versionedDir($view, $version) . '/.history/redo') !== null;
    }

    private function versionedDir(View $view, string $version): string
    {
        return $this->pathResolver->versionedDir($view, $version);
    }

    private function newSnapshotId(): string
    {
        return date('YmdHis') . '_' . random_int(1000, 9999);
    }

    private function pushCurrentTo(View $view, string $version, string $stackDir): void
    {
        $dir = $this->versionedDir($view, $version);
        $name = $view->getName();
        $design = $dir . '/' . $name . '.design.twig';
        $html = $dir . '/' . $name . '.html.twig';

        $fs = new Filesystem();
        $snapDir = $stackDir . '/' . $this->newSnapshotId();
        $fs->mkdir($snapDir, 0755);
        if (file_exists($design)) {
            $fs->copy($design, $snapDir . '/' . $name . '.design.twig');
        }
        if (file_exists($html)) {
            $fs->copy($html, $snapDir . '/' . $name . '.html.twig');
        }
        $this->trimStack($stackDir);
    }

    private function restoreSnapshot(View $view, string $version, string $snapDir): void
    {
        $dir = $this->versionedDir($view, $version);
        $name = $view->getName();
        $fs = new Filesystem();

        foreach (['design.twig', 'html.twig'] as $ext) {
            $src = $snapDir . '/' . $name . '.' . $ext;
            $dst = $dir . '/' . $name . '.' . $ext;
            if (file_exists($src)) {
                $fs->copy($src, $dst, true);
            }
        }
    }

    /**
     * @return string|null 返回快照目录绝对路径
     */
    private function latestSnapshot(string $stackDir): ?string
    {
        if (!is_dir($stackDir)) {
            return null;
        }
        $entries = glob($stackDir . '/*', GLOB_ONLYDIR);
        if (!$entries) {
            return null;
        }
        // 按名字倒序取最新
        rsort($entries);
        return $entries[0];
    }

    private function removeSnapshot(string $snapDir): void
    {
        $fs = new Filesystem();
        $fs->remove($snapDir);
    }

    private function trimStack(string $stackDir): void
    {
        if (!is_dir($stackDir)) {
            return;
        }
        $entries = glob($stackDir . '/*', GLOB_ONLYDIR);
        if (!$entries) {
            return;
        }
        rsort($entries);
        $fs = new Filesystem();
        foreach (array_slice($entries, self::MAX_SNAPSHOTS) as $old) {
            $fs->remove($old);
        }
    }

    private function clearStack(string $stackDir): void
    {
        $fs = new Filesystem();
        if (is_dir($stackDir)) {
            $fs->remove($stackDir);
        }
        $fs->mkdir($stackDir, 0755);
    }
}
