<?php

namespace App\Twig\Extension;

use App\Entity\Platform\View;
use App\Service\Form\FormLayoutService;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * 表单片段复用：{{ view_form(viewId, data, options) }}
 * 在宿主模板任意位置嵌入一个表单视图（render_mode=fragment/page 均渲染 <form> 片段）。
 */
class ViewFormExtension extends AbstractExtension
{
    public function __construct(
        private readonly FormLayoutService $formLayoutService,
        private readonly EntityManagerInterface $em,
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('view_form', [$this, 'renderForm'], ['is_safe' => ['html']]),
        ];
    }

    public function renderForm(string $viewId, ?object $data = null, array $options = []): string
    {
        $view = $this->em->getRepository(View::class)->find($viewId);
        if (!$view || !$view->getFormEntity()) {
            return '';
        }

        if ($data === null) {
            $fqn = $view->getFormEntity()->getFqn();
            $data = class_exists($fqn) ? new $fqn() : (object) [];
        }

        return $this->formLayoutService->renderFragment($view, $data, $options);
    }
}
