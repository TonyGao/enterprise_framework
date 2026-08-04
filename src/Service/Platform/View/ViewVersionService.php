<?php

namespace App\Service\Platform\View;

use App\Entity\Platform\View;
use App\Entity\Platform\ViewVersion;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * 视图版本创建服务：供版本管理 API 与 AI 重构流程共用，
 * 保证「新建版本」逻辑一致（复制来源版本文件 + 切换当前版本）。
 */
class ViewVersionService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ViewPathResolver $pathResolver,
    ) {}

    /**
     * 新建版本并切换为当前版本。
     *
     * @param string $fromVersion 'blank' 表示空白版本；'' 表示从当前激活版本复制；合法版本号表示从该版本复制
     * @param string|null $targetVersion 指定目标版本号；null 自动取来源 minor+1 的第一个未占用号
     * @param string|null $label 版本标签；null 使用默认 '版本 {target}'
     * @throws \InvalidArgumentException 来源目录不存在 / 目标版本已存在 / 版本号格式非法
     */
    public function createVersion(View $view, string $fromVersion = '', ?string $targetVersion = null, ?string $label = null): ViewVersion
    {
        $fs = new Filesystem();

        if ($fromVersion === 'blank') {
            $source = 'blank';
        } elseif ($fromVersion === '') {
            $source = $this->pathResolver->currentVersion($view);
        } else {
            $source = $fromVersion;
        }

        $existing = [];
        foreach ($view->getVersions() as $vv) {
            $existing[] = $vv->getVersion();
        }

        $target = $targetVersion;
        if ($target === null || $target === '') {
            $base = $source === 'blank' ? $this->pathResolver->currentVersion($view) : $source;
            $target = VersionNumber::incrementMinor($base);
            while (in_array($target, $existing, true)) {
                $target = VersionNumber::incrementMinor($target);
            }
        }

        if (!VersionNumber::isValid($target)) {
            throw new \InvalidArgumentException('版本号格式无效，应为 {major}_{minor}');
        }
        if (in_array($target, $existing, true)) {
            throw new \InvalidArgumentException("版本 $target 已存在");
        }

        $name = $view->getName();
        $newDir = $this->pathResolver->versionedDir($view, $target);
        $fs->mkdir($newDir, 0755);

        if ($source === 'blank') {
            $fs->dumpFile($newDir . '/' . $name . '.html.twig', '{# ' . $view->getLabel() . ' 视图模板 #}' . "\n"
                . '{% extends "base.html.twig" %}' . "\n\n"
                . '{% block body %}' . "\n"
                . '  {# 视图内容 #}' . "\n"
                . '{% endblock %}');
            $fs->dumpFile($newDir . '/' . $name . '.design.twig', '');
        } else {
            $srcDir = $this->pathResolver->versionedDir($view, $source);
            if (!is_dir($srcDir)) {
                throw new \InvalidArgumentException("来源版本 $source 目录不存在");
            }
            foreach (['design.twig', 'html.twig'] as $ext) {
                $srcFile = $srcDir . '/' . $name . '.' . $ext;
                $dstFile = $newDir . '/' . $name . '.' . $ext;
                if (file_exists($srcFile)) {
                    $fs->copy($srcFile, $dstFile);
                }
            }
        }

        foreach ($view->getVersions() as $vv) {
            $vv->setIsCurrent(false);
        }
        $version = new ViewVersion();
        $version->setView($view);
        $version->setVersion($target);
        $version->setLabel($label ?: '版本 ' . $target);
        $version->setIsCurrent(true);
        $view->addVersion($version);
        $view->setCurrentVersion($target);

        $this->em->persist($view);
        $this->em->flush();

        return $version;
    }
}
