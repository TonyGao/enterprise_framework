<?php

namespace App\Service\Platform\View;

use App\Entity\Platform\View;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * 视图文件定位器：统一按「path + current_version + name」解析 twig 文件路径。
 * 视图版本化后，所有读写 .design.twig / .html.twig 的地方都必须经由这里，
 * 避免各调用方自行拼路径造成不一致。
 */
class ViewPathResolver
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {}

    public function baseDir(): string
    {
        return $this->projectDir . '/templates/views/';
    }

    /**
     * 视图目录基路径（不含版本段）。
     */
    public function basePath(View $view): string
    {
        return trim((string) $view->getPath(), '/');
    }

    /**
     * 当前激活版本号。
     */
    public function currentVersion(View $view): string
    {
        $v = $view->getCurrentVersion();
        return ($v && VersionNumber::isValid($v)) ? $v : '1_0';
    }

    /**
     * 指定版本的目录相对路径，如 组织架构/xxx/1_0。
     */
    public function versionedPath(View $view, ?string $version = null): string
    {
        $base = $this->basePath($view);
        if ($base === '') {
            return '';
        }
        return $base . '/' . ($version ?: $this->currentVersion($view));
    }

    /**
     * 版本目录的绝对路径。
     */
    public function versionedDir(View $view, ?string $version = null): string
    {
        $relative = $this->versionedPath($view, $version);
        return $relative === '' ? $this->baseDir() : $this->baseDir() . $relative;
    }

    /**
     * 设计文件绝对路径（.design.twig）。内置视图无 path 时指向 builtin 目录。
     */
    public function designFile(View $view, ?string $version = null): ?string
    {
        if ($view->isBuiltIn() && !$view->getPath()) {
            return $this->baseDir() . 'builtin/' . $view->getName() . '.design.twig';
        }
        if (!$view->getName()) {
            return null;
        }
        $relative = $this->versionedPath($view, $version);
        if ($relative === '') {
            return null;
        }
        return $this->baseDir() . $relative . '/' . $view->getName() . '.design.twig';
    }

    /**
     * 运行时模板绝对路径（.html.twig）。内置视图无运行时文件返回 null。
     */
    public function htmlFile(View $view, ?string $version = null): ?string
    {
        if ($view->isBuiltIn() && !$view->getPath()) {
            return null;
        }
        if (!$view->getName()) {
            return null;
        }
        $relative = $this->versionedPath($view, $version);
        if ($relative === '') {
            return null;
        }
        return $this->baseDir() . $relative . '/' . $view->getName() . '.html.twig';
    }
}
