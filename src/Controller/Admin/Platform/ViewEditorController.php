<?php

namespace App\Controller\Admin\Platform;

use App\Entity\Platform\View;
use App\Controller\BaseController;
use App\Form\Platform\ViewEditType;
use App\Form\Platform\ViewFolderType;
use App\Form\Platform\ViewType;
use App\Lib\Str;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

class ViewEditorController extends BaseController
{
  /**
   * 视图管理界面
   *
   * @return Response
   */
  #[Route('/admin/platform/view/index', name: 'platform_view')]
  public function index(EntityManagerInterface $em): Response
  {
    $repo = $em->getRepository(View::class);
    $treeData = $repo->childrenHierarchy(null, false, [], true);
    $projectDir = $this->getParameter('kernel.project_dir');
    $configFile = $projectDir . '/var/data/view_editor_config.json';
    $generalConfig = [];
    if (file_exists($configFile)) {
        $configData = json_decode(file_get_contents($configFile), true);
        if (!empty($configData)) {
            $generalConfig = $configData;
        }
    }
    return $this->render('admin/platform/view/index.html.twig', [
      'treeData' => $treeData,
      'generalConfig' => $generalConfig,
    ]);
  }

  /**
   * 视图详情预览
   */
  #[Route('/admin/platform/view/detail', name: 'platform_view_detail')]
  public function viewDetail(Request $request, EntityManagerInterface $em): Response
  {
    $id = $request->query->get('id');
    if (!$id) {
      return new JsonResponse(['message' => '视图ID不能为空'], 400);
    }

    $view = $em->getRepository(View::class)->find($id);
    if (!$view || $view->getType() !== 'view') {
      return new JsonResponse(['message' => '视图不存在'], 404);
    }

    $fields = $em->getRepository(\App\Entity\Platform\ViewField::class)
      ->findBy(['view' => $view], ['sortOrder' => 'ASC']);

    $projectDir = $this->getParameter('kernel.project_dir');
    $configFile = $projectDir . '/var/data/view_editor_config.json';
    $generalConfig = [];
    if (file_exists($configFile)) {
      $configData = json_decode(file_get_contents($configFile), true);
      if (!empty($configData)) {
        $generalConfig = $configData;
      }
    }

    // Build preview URL for entity-bound views
    $previewUrl = null;
    if ($view->getFormEntity()) {
      $previewUrl = $this->generateUrl('platform_view_editor', ['id' => $view->getId()]);
    }

    return $this->render('admin/platform/view/view_detail.html.twig', [
      'view' => $view,
      'fields' => $fields,
      'generalConfig' => $generalConfig,
      'previewUrl' => $previewUrl,
    ]);
  }

  /**
   * 新建文件夹的表单
   *
   * @param Request $request
   * @param EntityManagerInterface $em
   * @return Response
   */
  #[Route('/admin/platform/view/addFolder', name: 'platform_view_add_folder')]
  public function addFolder(Request $request, EntityManagerInterface $em): Response
  {
    $parentId = $request->query->get('parent');
    $view = new View();
    $view->setType('folder'); // 新建的类型为文件夹

    // 处理上级目录的逻辑
    if ($parentId) {
      $parent = $em->getRepository(View::class)->find($parentId);
      if ($parent && ($parent->getType() === 'folder' || $parent->getType() === 'root')) {
        $view->setParent($parent);
      } else {
        return new JsonResponse(['message' => '上级目录无效或不是文件夹'], 400);
      }
    } else {
      // 如果没有父目录，创建根目录
      $root = $em->getRepository(View::class)->findOneBy(['name' => 'root']);
      if ($root === null) {
        $root = new View();
        $root->setName('root');
        $root->setLabel('Root');
        $root->setType('root');
        $em->persist($root);
        $em->flush();
        $view->setParent($root);
      } else {
        $view->setParent($root);
      }
    }

    // 创建表单并处理请求
    $form = $this->createForm(ViewFolderType::class, $view, [
      'action' => $this->generateUrl('platform_view_add_folder')
    ]);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
      // 检查同级目录下是否有同名文件夹
      $parent = $view->getParent();
      $existingFolder = $em->getRepository(View::class)->findOneBy([
        'parent' => $parent,
        'name' => $view->getName(),
        'type' => 'folder'
      ]);

      if ($existingFolder) {
        return new JsonResponse(['message' => '同级目录下已存在同名文件夹'], 400);
      }

      // 构建文件夹路径
      $basePath = $this->getParameter('kernel.project_dir') . '/templates/views';
      $relativePath = $this->buildRelativePath($parent, $view->getName());
      $folderPath = $basePath . '/' . $relativePath;
      
      // 检查文件系统中是否已存在该目录
      if (file_exists($folderPath) && is_dir($folderPath)) {
        return new JsonResponse(['message' => '文件系统中已存在同名文件夹'], 400);
      }
      
      // 创建文件夹
      if (!file_exists($folderPath)) {
        if (!mkdir($folderPath, 0755, true)) {
          return new JsonResponse(['message' => '创建文件夹失败，请检查权限'], 500);
        }
      }
      
      // 设置相对路径到数据库
      $view->setPath($relativePath);
      
      $em->persist($view);
      $em->flush();

      $this->addFlash('success', '文件夹创建成功');
      return $this->redirectToRoute('platform_view'); // 重定向到视图管理页面
    }

    return $this->render('admin/platform/view/add_folder.html.twig', [
      'form' => $form->createView(),
    ]);
  }

  /**
   * 新建视图的表单
   * 
   * @param Request $request
   * @param EntityManagerInterface $em
   * @return Response
   */
  #[Route('/admin/platform/view/addView', name: 'platform_view_add_view')]
  public function addView(Request $request, EntityManagerInterface $em): Response
  {
    $parentId = $request->query->get('parent');
    $view = new View();
    $view->setType('view'); // 新建的类型为视图

    // 处理上级目录的逻辑
    if ($parentId) {
      $parent = $em->getRepository(View::class)->find($parentId);
      if ($parent && ($parent->getType() === 'folder' || $parent->getType() === 'root')) {
        $view->setParent($parent);
      } else {
        return new JsonResponse(['message' => '上级目录无效或不是文件夹'], 400);
      }
    } else {
      // 如果没有父目录，创建根目录
      $root = $em->getRepository(View::class)->findOneBy(['name' => 'root']);
      if ($root === null) {
        $root = new View();
        $root->setName('root');
        $root->setLabel('Root');
        $root->setType('root');
        $em->persist($root);
        $em->flush();
        $view->setParent($root);
      } else {
        $view->setParent($root);
      }
    }

    // 创建表单并处理请求
    $showBuiltIn = $this->isGranted('ROLE_SYS_ADMIN');
    $form = $this->createForm(ViewType::class, $view, [
      'action' => $this->generateUrl('platform_view_add_view'),
      'show_built_in' => $showBuiltIn,
    ]);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
      // 检查同级目录下是否有同名视图
      $parent = $view->getParent();
      $existingView = $em->getRepository(View::class)->findOneBy([
        'parent' => $parent,
        'name' => $view->getName(),
        'type' => 'view'
      ]);

      if ($existingView) {
        return new JsonResponse(['message' => '同级目录下已存在同名视图'], 400);
      }

      if ($view->isBuiltIn()) {
        // 系统内置视图：跳过文件创建，使用预设的模板
        $view->setPath(null);
      } else {
        // 构建视图文件路径
        $basePath = $this->getParameter('kernel.project_dir') . '/templates/views';
        $relativePath = $this->buildRelativePath($parent, $view->getName());
        
        // 创建视图目录结构：视图名/1_0/
        $name = $view->getName();
        // 在原目录下创建以视图名命名的文件夹
        $viewFolderPath = $basePath . '/' . $relativePath;
        // 在视图名文件夹下创建版本控制目录 1_0 表示 v1.0
        $versionFolderPath = $viewFolderPath . '/1_0';
        
        // 确保视图目录存在
        if (!file_exists($viewFolderPath)) {
          if (!mkdir($viewFolderPath, 0755, true)) {
            return new JsonResponse(['message' => '创建视图目录失败，请检查权限'], 500);
          }
        }
        
        // 创建版本目录
        if (!file_exists($versionFolderPath)) {
          if (!mkdir($versionFolderPath, 0755, true)) {
            return new JsonResponse(['message' => '创建版本目录失败，请检查权限'], 500);
          }
        }
        
        // 创建两个视图文件：1_0/name.html.twig 和 1_0/name.design.twig
        $htmlTwigPath = $versionFolderPath . '/' . $name . '.html.twig';
        $designTwigPath = $versionFolderPath . '/' . $name . '.design.twig';
        
        // 检查文件是否已存在
        if (file_exists($htmlTwigPath) || file_exists($designTwigPath)) {
          return new JsonResponse(['message' => '文件系统中已存在同名视图文件'], 400);
        }
        
        // 创建视图文件
        if (file_put_contents($htmlTwigPath, '{# ' . $view->getLabel() . ' 视图模板 #}\n{% extends "base.html.twig" %}\n\n{% block body %}\n  {# 视图内容 #}\n{% endblock %}') === false) {
          return new JsonResponse(['message' => '创建视图HTML文件失败'], 500);
        }
        
        if (file_put_contents($designTwigPath, '{# ' . $view->getLabel() . ' 设计文件 #}\n{# 此文件用于存储视图设计信息 #}') === false) {
          // 如果设计文件创建失败，删除已创建的HTML文件
          if (file_exists($htmlTwigPath)) {
            unlink($htmlTwigPath);
          }
          return new JsonResponse(['message' => '创建视图设计文件失败'], 500);
        }
        
        // 设置相对路径到数据库（包含版本目录）
        $relativePath = $relativePath . '/1_0';
        $view->setPath($relativePath);
      }
      
      $em->persist($view);
      $em->flush();

      $this->addFlash('success', '视图创建成功');
      return $this->redirectToRoute('platform_view'); // 重定向到视图管理页面
    }

    return $this->render('admin/platform/view/add_view.html.twig', [
      'form' => $form->createView(),
    ]);
  }

  /**
   * 编辑视图的表单
   */
  #[Route('/admin/platform/view/editView', name: 'platform_view_edit_view')]
  public function editView(Request $request, EntityManagerInterface $em): Response
  {
    $viewId = $request->query->get('id');
    if (!$viewId) {
      return new JsonResponse(['message' => '视图ID不能为空'], 400);
    }

    $view = $em->getRepository(View::class)->find($viewId);
    if (!$view) {
      return new JsonResponse(['message' => '视图不存在'], 404);
    }

    $showBuiltIn = $this->isGranted('ROLE_SYS_ADMIN');
    $form = $this->createForm(ViewEditType::class, $view, [
      'action' => $this->generateUrl('platform_view_edit_view', ['id' => $viewId]),
      'show_built_in' => $showBuiltIn,
    ]);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
      $em->flush();
      $this->addFlash('success', '视图更新成功');
      return $this->redirectToRoute('platform_view');
    }

    return $this->render('admin/platform/view/edit_view.html.twig', [
      'view' => $view,
      'form' => $form->createView(),
    ]);
  }

  /**
   * 重命名文件夹的表单
   */
  #[Route('/admin/platform/view/renameFolder', name: 'platform_view_rename_folder')]
  public function renameFolder(Request $request, EntityManagerInterface $em): Response
  {
    $folderId = $request->query->get('id');
    if (!$folderId) {
      return new JsonResponse(['message' => '文件夹ID不能为空'], 400);
    }

    $folder = $em->getRepository(View::class)->find($folderId);
    if (!$folder || $folder->getType() !== 'folder') {
      return new JsonResponse(['message' => '文件夹不存在'], 404);
    }

    $form = $this->createForm(\App\Form\Platform\ViewFolderRenameType::class, $folder, [
      'action' => $this->generateUrl('platform_view_rename_folder', ['id' => $folderId]),
    ]);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
      // 检查同级目录下是否有同名文件夹
      $existing = $em->getRepository(View::class)->findOneBy([
        'parent' => $folder->getParent(),
        'name' => $folder->getName(),
        'type' => 'folder',
      ]);
      if ($existing && $existing->getId() !== $folder->getId()) {
        return new JsonResponse(['message' => '同级目录下已存在同名文件夹'], 400);
      }

      $em->flush();
      $this->addFlash('success', '文件夹重命名成功');

      if ($request->isXmlHttpRequest()) {
        return new JsonResponse(['success' => true]);
      }
      return $this->redirectToRoute('platform_view');
    }

    // 表单未提交或验证失败 — POST AJAX 返回 JSON，GET 直接返回 HTML
    if ($request->isMethod('POST') && $request->isXmlHttpRequest()) {
      $html = $this->renderView('admin/platform/view/rename_folder.html.twig', [
        'folder' => $folder,
        'form' => $form->createView(),
      ]);
      return new JsonResponse(['html' => $html]);
    }

    return $this->render('admin/platform/view/rename_folder.html.twig', [
      'folder' => $folder,
      'form' => $form->createView(),
    ]);
  }
  
  /**
   * 构建相对路径
   * 
   * @param View $parent 父节点
   * @param string $name 当前节点名称
   * @return string 相对路径
   */
  private function buildRelativePath(View $parent, string $name): string
  {
    $path = $name;
    
    // 如果父节点是根节点，直接返回当前名称
    if ($parent->getType() === 'root') {
      return $path;
    }
    
    // 递归构建路径
    $currentParent = $parent;
    $segments = [];
    
    while ($currentParent && $currentParent->getType() !== 'root') {
      array_unshift($segments, $currentParent->getName());
      $currentParent = $currentParent->getParent();
    }
    
    // 拼接路径
    if (!empty($segments)) {
      $path = implode('/', $segments) . '/' . $path;
    }
    
    return $path;
  }

  #[Route(
    '/admin/platform/view/editor/{id}',
    name: 'platform_view_editor'
  )]
  public function editor(
    string $id,
    \App\Service\Form\FormFieldRenderer $formFieldRenderer,
    \Doctrine\ORM\EntityManagerInterface $em,
    #[Autowire('%kernel.project_dir%')] string $projectDir
  ): Response {
    $components = [
      ['icon' => 'fa-solid fa-border-none', 'name' => '布局', 'componentType' => 'layout'],
      ['icon' => 'fa-solid fa-table', 'name' => '表格', 'componentType' => 'table'],
      ['icon' => 'fa-solid fa-text-height', 'name' => '文本', 'componentType' => 'text'],
      ['icon' => 'fa-solid fa-image', 'name' => '图片', 'componentType' => 'image'],
      ['icon' => 'fa-regular fa-newspaper', 'name' => '富文本', 'componentType' => 'rich_text'],
      ['icon' => 'fa-solid fa-video', 'name' => '视频', 'componentType' => 'video'],
      ['icon' => 'fa-solid fa-mattress-pillow', 'name' => '按钮', 'componentType' => 'button'],
      ['icon' => 'fa-solid fa-divide', 'name' => 'Divider', 'componentType' => 'divider'],
      ['icon' => 'fa-solid fa-arrows-up-to-line', 'name' => 'Spacer', 'componentType' => 'spacer'],
      ['icon' => 'fa-solid fa-map-location-dot', 'name' => '地图', 'componentType' => 'map'],
      ['icon' => 'fa-solid fa-map', 'name' => 'Icon', 'componentType' => 'icon'],
      ['icon' => 'fa-solid fa-map', 'name' => '相册', 'componentType' => 'gallery'],
    ];

    $tplVars = ['components' => $components];

    $configFile = $projectDir . '/var/data/view_editor_config.json';
    if (file_exists($configFile)) {
        $configData = json_decode(file_get_contents($configFile), true);
        if (!empty($configData)) {
            $tplVars['generalConfig'] = $configData;
        }
    }

    $view = $em->getRepository(\App\Entity\Platform\View::class)->find($id);
    if ($view) {
      $tplVars['sectionConfig'] = $view->getSectionConfig();
    }
    if ($view && $view->getFormEntity() && $view->isBuiltIn()) {
      $tplVars['formEntity'] = $view->getFormEntity();
      $tplVars['entityProperties'] = $em->getRepository(\App\Entity\Platform\EntityProperty::class)
        ->findBy(['entity' => $view->getFormEntity()], ['orderNum' => 'ASC']);

      $fields = $em->getRepository(\App\Entity\Platform\ViewField::class)
        ->findBy(['view' => $view], ['sortOrder' => 'ASC']);
      if (!empty($fields)) {
        try {
          $entityClass = $view->getFormEntity()->getFqn();
          $data = new $entityClass();
          $result = $formFieldRenderer->render($view, $data, true, [], $tplVars['generalConfig'] ?? []);
          $tplVars['initialCanvasHtml'] = $result['html'];
        } catch (\Exception $e) {
          // fallback to empty canvas
        }
      }
    }
  
    return $this->render('admin/platform/view/editor.html.twig', $tplVars);
  }
  
  #[Route(
    '/admin/platform/view/neweditor/{id}',
    name: 'platform_view_new_editor'
  )]
  public function newEditor(string $id): Response
  {
    $components = [
      ['icon' => 'fa-solid fa-border-none', 'name' => '布局', 'componentType' => 'layout'],
      ['icon' => 'fa-solid fa-table', 'name' => '表格', 'componentType' => 'table'],
      ['icon' => 'fa-solid fa-text-height', 'name' => '文本', 'componentType' => 'text'],
      ['icon' => 'fa-solid fa-image', 'name' => '图片', 'componentType' => 'image'],
      ['icon' => 'fa-regular fa-newspaper', 'name' => '富文本', 'componentType' => 'rich_text'],
      ['icon' => 'fa-solid fa-video', 'name' => '视频', 'componentType' => 'video'],
      ['icon' => 'fa-solid fa-mattress-pillow', 'name' => '按钮', 'componentType' => 'button'],
      ['icon' => 'fa-solid fa-divide', 'name' => 'Divider', 'componentType' => 'divider'],
      ['icon' => 'fa-solid fa-arrows-up-to-line', 'name' => 'Spacer', 'componentType' => 'spacer'],
      ['icon' => 'fa-solid fa-map-location-dot', 'name' => '地图', 'componentType' => 'map'],
      ['icon' => 'fa-solid fa-map', 'name' => 'Icon', 'componentType' => 'icon'],
      ['icon' => 'fa-solid fa-map', 'name' => '相册', 'componentType' => 'gallery'],
    ];
  
    return $this->render('admin/platform/view/editor.html.twig', [
      'components' => $components,
    ]);
  }
}
