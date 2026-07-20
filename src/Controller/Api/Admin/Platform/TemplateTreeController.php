<?php

namespace App\Controller\Api\Admin\Platform;

use App\Controller\BaseController;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class TemplateTreeController extends BaseController
{
  #[Route('/api/admin/platform/templates/tree', name: 'api_platform_templates_tree')]
  public function tree(): JsonResponse
  {
    $templateDir = $this->getParameter('kernel.project_dir') . '/templates';
    $tree = $this->buildTree($templateDir);
    return $this->json($tree);
  }

  private function buildTree(string $dir): array
  {
    $entries = [];
    $iterator = new \FilesystemIterator($dir, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::UNIX_PATHS);
    $files = iterator_to_array($iterator);
    ksort($files);

    foreach ($files as $path => $fileInfo) {
      $name = $fileInfo->getFilename();

      if ($name === 'views') {
        continue;
      }

      if ($fileInfo->isDir()) {
        $children = $this->buildTree($fileInfo->getPathname());
        if (!empty($children)) {
          $entries[] = [
            'name' => $name,
            'type' => 'directory',
            'children' => $children,
          ];
        }
      } elseif (str_ends_with($name, '.html.twig')) {
        $relativePath = substr($fileInfo->getPathname(), strlen($this->getParameter('kernel.project_dir') . '/templates/'));
        $entries[] = [
          'name' => $name,
          'type' => 'file',
          'path' => $relativePath,
        ];
      }
    }

    return $entries;
  }
}
