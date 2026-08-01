<?php

namespace App\Service\AI\Tool;

use App\Entity\Platform\View;
use App\Service\Utils\DomManipulator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * 视图文件直写型工具：异步 AI 二次加工时没有打开的编辑器页面，
 * 无法使用 CDP，因此直接读写视图的 .design.twig / .html.twig 文件。
 */
#[AsTool(name: 'viewfile_getDesign', description: '读取视图设计文件内容（.section-content 内部 HTML）及文件路径', method: 'getDesign')]
#[AsTool(name: 'viewfile_writeDesign', description: '将完整的页面内容写入视图设计文件（html 只包含页面内容，不含外层 section 包裹器）', method: 'writeDesign')]
#[AsTool(name: 'viewfile_renderHtml', description: '根据当前设计文件重新生成可执行模板（.html.twig），清理编辑器专用标记', method: 'renderHtml')]
class ViewFileToolProvider
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly DomManipulator $domManipulator,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {}

    private function findView(string $idOrName): ?View
    {
        $view = $this->em->getRepository(View::class)->find($idOrName);
        if ($view) {
            return $view;
        }
        return $this->em->getRepository(View::class)->findOneBy(['name' => $idOrName]);
    }

    /**
     * @return array{designFile?: string, htmlFile?: string, builtIn?: bool} 视图文件路径信息
     */
    private function resolveFiles(View $view): array
    {
        $viewPath = $view->getPath();
        $viewName = $view->getName();
        $base = $this->projectDir . '/templates/views/';

        if ($view->isBuiltIn() && !$viewPath) {
            return ['designFile' => $base . 'builtin/' . $viewName . '.design.twig', 'builtIn' => true];
        }

        return [
            'designFile' => $base . $viewPath . '/' . $viewName . '.design.twig',
            'htmlFile' => $base . $viewPath . '/' . $viewName . '.html.twig',
            'builtIn' => $view->isBuiltIn(),
        ];
    }

    private function extractSectionContentInnerHtml(string $html): string
    {
        $useErrors = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_use_internal_errors($useErrors);
        $xpath = new \DOMXPath($dom);
        $nodes = $xpath->query("//*[contains(@class, 'section-content')]");
        if ($nodes && $nodes->length > 0) {
            $inner = '';
            foreach ($nodes->item(0)->childNodes as $child) {
                $inner .= $dom->saveHTML($child);
            }
            return $inner;
        }
        return $html;
    }

    #[AsTool(
        name: 'viewfile.getDesign',
        description: '读取视图设计文件内容（.section-content 内部 HTML）及文件路径',
    )]
    public function getDesign(string $viewId): array
    {
        $view = $this->findView($viewId);
        if (!$view) {
            return ['error' => "视图 $viewId 不存在"];
        }

        $files = $this->resolveFiles($view);
        if (!isset($files['designFile']) || !file_exists($files['designFile'])) {
            return ['error' => '视图设计文件不存在', 'files' => $files];
        }

        $content = file_get_contents($files['designFile']);
        if ($content === false) {
            return ['error' => '读取设计文件失败'];
        }

        return [
            'viewId' => (string) $view->getId(),
            'viewName' => $view->getName(),
            'viewLabel' => $view->getLabel(),
            'builtIn' => $view->isBuiltIn(),
            'designFile' => str_replace($this->projectDir . '/', '', $files['designFile']),
            'designContent' => $this->extractSectionContentInnerHtml($content),
        ];
    }

    #[AsTool(
        name: 'viewfile.writeDesign',
        description: '将完整的页面内容写入视图设计文件。html 只包含页面内容本身（.section-content 内部），工具会自动包裹外层 section 结构',
    )]
    public function writeDesign(string $viewId, string $html): array
    {
        $view = $this->findView($viewId);
        if (!$view) {
            return ['error' => "视图 $viewId 不存在"];
        }

        $files = $this->resolveFiles($view);
        if (!isset($files['designFile'])) {
            return ['error' => '视图无设计文件路径'];
        }

        $wrapped = "<section class=\"section\">\n  <div class=\"section-content\">\n" . $html . "\n  </div>\n</section>";

        $dir = dirname($files['designFile']);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (file_put_contents($files['designFile'], $wrapped) === false) {
            return ['error' => '写入设计文件失败'];
        }

        return ['message' => '设计文件已更新', 'designFile' => str_replace($this->projectDir . '/', '', $files['designFile'])];
    }

    #[AsTool(
        name: 'viewfile.renderHtml',
        description: '根据当前设计文件重新生成可执行模板（.html.twig），清理所有编辑器专用标记。所有修改完成后必须调用一次',
    )]
    public function renderHtml(string $viewId): array
    {
        $view = $this->findView($viewId);
        if (!$view) {
            return ['error' => "视图 $viewId 不存在"];
        }

        $files = $this->resolveFiles($view);
        if (!isset($files['designFile']) || !file_exists($files['designFile'])) {
            return ['error' => '视图设计文件不存在'];
        }

        $content = file_get_contents($files['designFile']);
        if ($content === false) {
            return ['error' => '读取设计文件失败'];
        }

        $this->domManipulator->load($content);
        $this->domManipulator->remove('.section-controls');
        $this->domManipulator->remove('.add-section-button');
        $this->domManipulator->remove('.section-header');
        $this->domManipulator->removeClass('.section.active', 'active');
        $this->domManipulator->removeClass('.ui-droppable', 'ui-droppable');
        $this->domManipulator->removeClass('.ef-component-labels', 'ef-component-labels');
        $this->domManipulator->processTableCells();
        $this->domManipulator->processDynamicFields();
        $executableHtml = $this->domManipulator->getHtml();

        if ($view->isBuiltIn() && !isset($files['htmlFile'])) {
            return ['message' => '内置视图仅保存设计文件，无需生成可执行模板'];
        }

        $dir = dirname($files['htmlFile']);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (file_put_contents($files['htmlFile'], $executableHtml) === false) {
            return ['error' => '写入可执行模板失败'];
        }

        return [
            'message' => '可执行模板已生成',
            'htmlFile' => str_replace($this->projectDir . '/', '', $files['htmlFile']),
        ];
    }
}
