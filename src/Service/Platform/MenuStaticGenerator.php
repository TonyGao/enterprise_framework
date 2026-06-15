<?php

namespace App\Service\Platform;

use Twig\Environment;
use App\Entity\Platform\Menu;
use App\Service\BaseService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Filesystem;
use App\Service\Platform\CodeFormatterService;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

class MenuStaticGenerator extends BaseService
{
  private $em;
  private $twig;
  private $filesystem;
  private $projectDir;
  private CodeFormatterService $formatter;

  public function __construct(EntityManagerInterface $em, Environment $twig, CodeFormatterService $codeFormatterService, string $projectDir)
  {
    $this->em = $em;
    $this->twig = $twig;
    $this->filesystem = new Filesystem();
    $this->formatter = $codeFormatterService;
    $this->projectDir = $projectDir;
  }

  public function generateStaticMenu(?SymfonyStyle $io = null): void
  {
    $menuTwig = $this->projectDir .'/templates/admin/static/menu.html.twig';

    $repo = $this->em->getRepository(Menu::class);
    $root = $repo->childrenHierarchy();
    $menus = $root !== [] ? $root[0]['__children'] : [];

    // 过滤掉禁用的菜单及其子菜单
    $menus = $this->filterEnabledMenus($menus);

    $html = $this->twig->render('admin/dynamic/menu.html.twig', [
      'menus' => $menus
    ]);

    if (!$this->filesystem->exists($menuTwig)) {
      try {
        $this->filesystem->touch($menuTwig);
        if ($io) {
          $io->success('Created the menu twig file.');
        }
      } catch (IOExceptionInterface $exception) {
          if ($io) {
            $io->error("An error occurred while creating your file at " . $exception->getPath());
          } else {
              throw new \RuntimeException("An error occurred while creating your file at " . $exception->getPath());
          }
          return;
      }
    }

    try {
      $this->filesystem->dumpFile($menuTwig, $html);
      $this->formatter->formatFile($menuTwig);
      if ($io) {
        $io->success('Static menu file generated successfully.');
      }
    } catch (\Exception $exception) {
      $this->handleException($exception, $io);
    }
  }

  private function filterEnabledMenus(array $menus): array
  {
    $result = [];
    foreach ($menus as $menu) {
      if (($menu['enabled'] ?? true) !== true) {
        continue;
      }
      if (!empty($menu['__children'])) {
        $menu['__children'] = $this->filterEnabledMenus($menu['__children']);
      }
      $result[] = $menu;
    }
    return $result;
  }

  private function handleException(\Exception $exception, ?SymfonyStyle $io): void
  {
      if ($exception instanceof IOExceptionInterface) {
          $message = "An error occurred while handling a filesystem operation at " . $exception->getPath();
      } elseif ($exception instanceof ProcessFailedException) {
          $message = "An error occurred while formatting your file: " . $exception->getMessage();
      } else {
          $message = "An unexpected error occurred: " . $exception->getMessage();
      }

      if ($io) {
          $io->error($message);
      } else {
          throw new \RuntimeException($message);
      }
  }
}