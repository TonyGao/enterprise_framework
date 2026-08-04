<?php

namespace App\Command;

use App\Entity\Platform\View;
use App\Entity\Platform\ViewVersion;
use App\Service\Platform\View\VersionNumber;
use App\Service\Platform\View\ViewPathResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * 存量视图版本规范化（幂等）：
 * 1. 将 View.path 中末尾的版本段 {major}_{minor} 拆出为 current_version，path 改为视图目录基路径
 * 2. 为每个视图补建 platform_view_version 记录
 * 3. reconcile 文件系统中的版本目录
 */
#[AsCommand(name: 'app:view:normalize-versions', description: '规范化存量视图的版本数据结构（幂等）')]
class EfNormalizeViewVersionsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ViewPathResolver $pathResolver,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $em = $this->em;
        $em->getFilters()->disable('softdeleteable');

        $repo = $em->getRepository(View::class);
        $qb = $repo->createQueryBuilder('v')
            ->where('v.type = :type')
            ->andWhere('v.path IS NOT NULL')
            ->setParameter('type', 'view');
        $views = $qb->getQuery()->getResult();

        $splitCount = 0;
        $versionRowsAdded = 0;
        $fsAdded = 0;

        foreach ($views as $view) {
            // 无 path 的内置视图走 builtin 目录，不参与版本化
            if ($view->isBuiltIn() && !$view->getPath()) {
                continue;
            }

            $path = (string) $view->getPath();

            // 1. 拆分 path 末尾版本段
            if (preg_match('#^(.*?)/(\d+_\d+)$#', $path, $m)) {
                $basePath = $m[1];
                $version = $m[2];
                if (trim($basePath) !== '' && trim($basePath) !== $path) {
                    $view->setPath($basePath);
                    $view->setCurrentVersion($version);
                    $em->persist($view);
                    $splitCount++;
                }
            }

            $currentVersion = $view->getCurrentVersion();
            if (!$currentVersion || !VersionNumber::isValid($currentVersion)) {
                $currentVersion = '1_0';
                $view->setCurrentVersion($currentVersion);
                $em->persist($view);
            }

            // 2. 确保当前版本有记录
            $has = false;
            foreach ($view->getVersions() as $vv) {
                if ($vv->getVersion() === $currentVersion) {
                    $has = true;
                    $vv->setIsCurrent(true);
                } else {
                    $vv->setIsCurrent(false);
                }
            }
            if (!$has) {
                $vv = new ViewVersion();
                $vv->setView($view);
                $vv->setVersion($currentVersion);
                $vv->setLabel('v' . $currentVersion);
                $vv->setIsCurrent(true);
                $view->addVersion($vv);
                $em->persist($vv);
                $versionRowsAdded++;
            }

            // 3. reconcile 文件系统版本目录
            $dir = $this->pathResolver->versionedPath($view);
            if ($dir !== '') {
                $abs = $this->pathResolver->baseDir() . $dir;
                if (is_dir($abs)) {
                    $existing = array_map(fn($vv) => $vv->getVersion(), $view->getVersions()->toArray());
                    foreach (scandir($abs) ?: [] as $entry) {
                        if (!VersionNumber::isValid($entry) || in_array($entry, $existing, true)) {
                            continue;
                        }
                        $vv = new ViewVersion();
                        $vv->setView($view);
                        $vv->setVersion($entry);
                        $vv->setLabel('v' . $entry);
                        $vv->setIsCurrent($entry === $currentVersion);
                        $view->addVersion($vv);
                        $em->persist($vv);
                        $fsAdded++;
                    }
                }
            }
        }

        $em->flush();

        $io->success(sprintf(
            '规范化完成：拆分 path=%d，补建版本记录=%d，文件系统补录=%d',
            $splitCount,
            $versionRowsAdded,
            $fsAdded
        ));

        return Command::SUCCESS;
    }
}
