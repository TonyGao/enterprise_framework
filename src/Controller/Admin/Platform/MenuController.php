<?php

namespace App\Controller\Admin\Platform;

use App\Entity\Platform\Menu;
use App\Form\Platform\MenuType;
use App\Controller\BaseController;
use App\Service\Platform\MenuStaticGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class MenuController extends BaseController
{
  private $menuStaticGenerator;

  public function __construct(MenuStaticGenerator $menuStaticGenerator)
  {
      $this->menuStaticGenerator = $menuStaticGenerator;
  }

  /**
   * 菜单管理首页
   */
  #[Route('/admin/platform/menu/index', name: 'platform_menu')]
  public function index(EntityManagerInterface $em): Response
  {
    $repo = $em->getRepository(Menu::class);
    $menu = $repo->childrenHierarchy(null, false, [
      'decorate' => true,
      'rootOpen' => static function (array $tree): ?string {
        if ([] !== $tree && 0 == $tree[0]['lvl']) {
          return '<ol class="ol-left-tree">';
        }

        if ($tree[0]['lvl'] > 1) {
          return '<span class="tree-indent" style="display: none;"></span><ol class="sub-tree-content" style="display: none;">';
        }

        return '<span class="tree-indent"></span><ol class="sub-tree-content">';
      },
      'rootClose' => static function (array $child): ?string {
        return '</ol>';
      },
      'childOpen' => '<li>',
      'childClose' => '</li>',
      'nodeDecorator' => static function (array $node) use (&$controller): ?string {
        $arrayIcon = !empty($node['__children']) ? '<i class="fa-solid fa-caret-right"></i>' : '';

        if ($node['lvl'] === 0) {
          return '
          <div class="item-content scroll-item" style="font-weight: bold;">
            <div class="arrow-icon">' . $arrayIcon . '</div>
						<div class="org-icon">
              <i class="fa-regular fa-compass"></i>
						</div>
						<div class="node-name">
							<div class="tree-text-content branch" type="menu" id="' . $node['id'] . '">' .
        '系统菜单'
          . '</div>
						</div>
					</div>
          ';
        }

        return '
          <div class="item-content scroll-item">
            <div class="item-original">
              <div class="arrow-icon">' . $arrayIcon . '</div>
              <div class="org-icon">
                <label aria-disabled="false" class="ef-checkbox"><input type="checkbox" class="ef-checkbox-target" value="0">
                  <span class="ef-icon-hover ef-checkbox-icon-hover">
                    <div class="ef-checkbox-icon"></div>
                  </span>
                  <span class="ef-checkbox-label"></span>
                </label>
              </div>
              <div class="node-name">
                <div class="tree-text-content branch" type="menu" id="' . $node['id'] . '">' .
            $node['label']
            . (($node['enabled'] ?? true) ? '' : ' <span style="color:#f53f3f;font-size:12px;">[已禁用]</span>')
            . '</div>
              </div>
              <div class="node-tail-icon">
                <div class="ef-group-field-handler">
                  <div class="ef-group-filed-handler-wrapper ui-sortable-handle">
                    <div class="circle">
                      <i class="fa-solid fa-arrows-up-down-left-right"></i>
                    </div>
                  </div>
                </div>
              </div>
            </div>
					</div>
          ';
      }
    ]);
    return $this->render('admin/platform/menu/index.html.twig', [
      'menu' => $menu
    ]);
  }

  #[Route('/admin/platform/menu/new', name: 'platform_menu_new_cache')]
  public function createMenu(Request $request, EntityManagerInterface $em): Response
  {
    $menu = new Menu();
    $form = $this->createForm(MenuType::class, $menu, [
      'action' => $this->generateUrl('platform_menu_new_cache')
    ]);

    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
      $menuPost = $form->getData();

      // 当这个菜单没有上级菜单时，需要赋值父级菜单为根菜单。
      // 也就是这个菜单是逻辑上的一级菜单。
      if ($menuPost->getParent() == null) {
        $repo = $em->getRepository(Menu::class);
        $root = $repo->findOneBy(['lvl' => 0]);
        $menuPost->setParent($root);
      }

      $em->persist($menuPost);
      $em->flush();

      // 调用 MenuStaticGenerator 服务来生成静态菜单
      try {
        $this->menuStaticGenerator->generateStaticMenu();
      } catch (\RuntimeException $e) {
          $this->addFlash('error', $e->getMessage());
      }

      return $this->redirectToRoute('platform_menu');
    }

    $f =  $form->createView();
    return $this->render('/admin/platform/menu/menuNew.html.twig', [
      'form' => $f,
    ]);
  }

  #[Route('/admin/platform/menu/{id}/edit', name: 'platform_menu_edit')]
  public function editMenu(string $id, Request $request, EntityManagerInterface $em): Response
  {
    $repo = $em->getRepository(Menu::class);
    $menu = $repo->find($id);

    if (!$menu) {
      throw $this->createNotFoundException('菜单不存在');
    }

    $form = $this->createForm(MenuType::class, $menu, [
      'action' => $this->generateUrl('platform_menu_edit', ['id' => $id])
    ]);

    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
      $em->flush();

      try {
        $this->menuStaticGenerator->generateStaticMenu();
      } catch (\RuntimeException $e) {
          $this->addFlash('error', $e->getMessage());
      }

      return $this->redirectToRoute('platform_menu');
    }

    $f = $form->createView();
    return $this->render('/admin/platform/menu/menuNew.html.twig', [
      'form' => $f,
    ]);
  }
}
