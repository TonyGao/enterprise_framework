<?php

namespace App\Controller\Admin\Platform;

use App\Entity\Platform\View;
use App\Service\Form\FormLayoutService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * 表单视图页面/片段渲染入口：
 * - GET /views/{id}            page 形态独立整页
 * - GET /api/views/{id}/form   表单片段（非 Twig 宿主/异步引入）
 */
class ViewPageController extends AbstractController
{
    public function __construct(
        private readonly FormLayoutService $formLayoutService,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('/api/admin/platform/view/{id}/render-mode', name: 'api_view_render_mode', methods: ['POST'])]
    public function setRenderMode(string $id, Request $request): Response
    {
        $view = $this->em->getRepository(View::class)->find($id);
        if (!$view) {
            return $this->json(['error' => 'msg.view.not_found'], 404);
        }

        $mode = (string) ($request->toArray()['mode'] ?? '');
        if (!in_array($mode, ['page', 'fragment'], true)) {
            return $this->json(['error' => 'msg.view.invalid_render_mode'], 400);
        }

        $sc = $view->getSectionConfig() ?? [];
        $sc['render_mode'] = $mode;
        $view->setSectionConfig($sc);
        $this->em->flush();

        return $this->json(['ok' => true, 'render_mode' => $mode]);
    }

    #[Route('/views/{id}', name: 'platform_view_page', methods: ['GET'])]
    public function page(string $id): Response
    {
        $view = $this->em->getRepository(View::class)->find($id);
        if (!$view || !$view->getFormEntity()) {
            throw $this->createNotFoundException('视图不存在或未绑定模型');
        }

        $fqn = $view->getFormEntity()->getFqn();
        $data = class_exists($fqn) ? new $fqn() : (object) [];

        return $this->render('admin/platform/view/view_page.html.twig', [
            'viewName' => $view->getLabel() ?: $view->getName(),
            'content' => $this->formLayoutService->renderPage($view, $data),
        ]);
    }

    #[Route('/api/views/{id}/form', name: 'api_view_form_fragment', methods: ['GET'])]
    public function formFragment(string $id): Response
    {
        $view = $this->em->getRepository(View::class)->find($id);
        if (!$view || !$view->getFormEntity()) {
            throw $this->createNotFoundException('视图不存在或未绑定模型');
        }

        $fqn = $view->getFormEntity()->getFqn();
        $data = class_exists($fqn) ? new $fqn() : (object) [];

        $html = $this->formLayoutService->renderFragment($view, $data, ['width' => 800]);

        return new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /**
     * 独立整页预览：不加后台布局/宽度限制，裸页面呈现视图设计（脚本、全宽、3D 均可正常）。
     * 适用于任何视图（表单或普通视图）。可刷新、可收藏。
     */
    #[Route('/views/{id}/preview', name: 'platform_view_preview', methods: ['GET'])]
    public function preview(string $id): Response
    {
        $view = $this->em->getRepository(View::class)->find($id);
        if (!$view) {
            throw $this->createNotFoundException('视图不存在');
        }

        $data = (object) [];
        if ($view->getFormEntity()) {
            $fqn = $view->getFormEntity()->getFqn();
            if ($fqn && class_exists($fqn)) {
                $data = new $fqn();
            }
        }

        $content = $this->formLayoutService->renderPage($view, $data);

        return new Response(
            '<!doctype html><html lang="zh-CN"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . htmlspecialchars($view->getLabel() ?: $view->getName() ?: 'View Preview', ENT_QUOTES) . '</title>'
            . '<style>html,body{margin:0;padding:0;}</style></head><body>'
            . $content
            . '</body></html>',
            200,
            ['Content-Type' => 'text/html; charset=UTF-8']
        );
    }
}
