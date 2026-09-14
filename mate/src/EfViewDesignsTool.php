<?php

namespace Mate;

use Symfony\AI\Mate\Attribute\MateTool;

/**
 * 列出 templates/views 下的自定义 Twig 视图设计 / Lists custom Twig view designs under templates/views.
 */
final class EfViewDesignsTool
{
    /**
     * 列出视图设计 / List view designs.
     *
     * @param string|null $module 可选：按模块目录过滤（如 "组织架构"）/ Optional: filter by module folder (e.g. "组织架构")
     */
    #[MateTool(
        name: 'ef-view-designs',
        title: 'Custom View Designs',
        description: 'List custom Twig view designs under templates/views (module, view name, versions and file paths), excluding .checkpoints and .history snapshots.',
    )]
    public function listDesigns(?string $module = null): string
    {
        $root = \dirname(__DIR__, 2).'/templates/views';
        if (!is_dir($root)) {
            return 'No templates/views directory found.';
        }

        $designs = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || !str_ends_with($file->getFilename(), '.design.twig')) {
                continue;
            }

            $relative = substr($file->getPathname(), \strlen($root) + 1);
            if ($this->isSnapshot($relative)) {
                continue;
            }

            $segments = explode('/', $relative);
            $viewModule = $segments[0] ?? '';
            if ($module !== null && $module !== '' && $viewModule !== $module) {
                continue;
            }

            $key = $viewModule.'/'.($segments[1] ?? '');
            $designs[$key]['module'] = $viewModule;
            $designs[$key]['name'] = $segments[1] ?? '';
            $designs[$key]['versions'][] = $segments[2] ?? '';
            $designs[$key]['files'][] = $relative;
        }

        if ($designs === []) {
            return 'No custom view designs found'.($module !== null && $module !== '' ? ' for module "'.$module.'"' : '').'.';
        }

        ksort($designs);

        $lines = ['Custom Twig view designs (templates/views):', ''];
        foreach ($designs as $design) {
            $versions = array_values(array_unique(array_filter($design['versions'])));
            sort($versions);
            $lines[] = sprintf('%s/%s  [versions: %s]', $design['module'], $design['name'], implode(', ', $versions));
            foreach ($design['files'] as $relative) {
                $lines[] = '    '.$relative;
            }
            $lines[] = '';
        }
        $lines[] = sprintf('Total: %d design(s)', \count($designs));

        return implode("\n", $lines);
    }

    private function isSnapshot(string $relative): bool
    {
        foreach (['.checkpoints/', '.history/', '/.checkpoints/', '/.history/'] as $marker) {
            if (str_contains($relative, $marker)) {
                return true;
            }
        }

        return false;
    }
}
