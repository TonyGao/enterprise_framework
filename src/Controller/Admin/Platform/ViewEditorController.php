<?php

namespace App\Controller\Admin\Platform;

use App\Entity\Platform\AiViewEnhanceTask;
use App\Entity\Platform\View;
use App\Controller\BaseController;
use App\Form\Platform\ViewEditType;
use App\Form\Platform\ViewFolderType;
use App\Form\Platform\ViewType;
use App\Lib\Str;
use App\Message\EnhanceViewMessage;
use Doctrine\ORM\EntityManagerInterface;
use DOMXPath;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
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
  public function viewDetail(Request $request, EntityManagerInterface $em, \App\Service\Platform\View\ViewPathResolver $pathResolver): Response
  {
    $id = $request->query->get('id');
    if (!$id) {
      return new JsonResponse(['message' => 'msg.view.id_required'], 400);
    }

    $view = $em->getRepository(View::class)->find($id);
    if (!$view || $view->getType() !== 'view') {
      return new JsonResponse(['message' => 'msg.view.not_found'], 404);
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
      'currentVersion' => $pathResolver->currentVersion($view),
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

    if (!$parentId && $request->isMethod('POST')) {
      $all = $request->request->all();
      foreach ($all as $data) {
        if (is_array($data) && isset($data['parent'])) {
          $parentId = $data['parent'];
          break;
        }
      }
    }

    $view = new View();
    $view->setType('folder'); // 新建的类型为文件夹

    // 处理上级目录的逻辑
    if ($parentId) {
      $parent = $em->getRepository(View::class)->find($parentId);
      if ($parent && ($parent->getType() === 'folder' || $parent->getType() === 'root')) {
        $view->setParent($parent);
      } else {
        return new JsonResponse(['message' => 'msg.view.invalid_parent'], 400);
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
        return new JsonResponse(['message' => 'msg.view.folder_name_exists'], 400);
      }

      // 构建文件夹路径
      $basePath = $this->getParameter('kernel.project_dir') . '/templates/views';
      $relativePath = $this->buildRelativePath($parent, $view->getName());
      $folderPath = $basePath . '/' . $relativePath;
      
      // 检查文件系统中是否已存在该目录
      if (file_exists($folderPath) && is_dir($folderPath)) {
        return new JsonResponse(['message' => 'msg.view.folder_fs_exists'], 400);
      }
      
      // 创建文件夹
      if (!file_exists($folderPath)) {
        if (!mkdir($folderPath, 0755, true)) {
          return new JsonResponse(['message' => 'msg.view.folder_create_failed'], 500);
        }
      }
      
      // 设置相对路径到数据库
      $view->setPath($relativePath);
      
      $em->persist($view);
      $em->flush();

      $this->addFlash('success', 'flash.folder_created');
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
  public function addView(Request $request, EntityManagerInterface $em, MessageBusInterface $bus): Response
  {
    $parentId = $request->query->get('parent');

    // For POST requests (form submission), also look for parent in request body (form prefix nesting)
    if (!$parentId && $request->isMethod('POST')) {
      $all = $request->request->all();
      foreach ($all as $data) {
        if (is_array($data) && isset($data['parent'])) {
          $parentId = $data['parent'];
          break;
        }
      }
    }

    $view = new View();
    $view->setType('view'); // 新建的类型为视图

    // 处理上级目录的逻辑
    if ($parentId) {
      $parent = $em->getRepository(View::class)->find($parentId);
      if ($parent && ($parent->getType() === 'folder' || $parent->getType() === 'root')) {
        $view->setParent($parent);
      } else {
        return new JsonResponse(['message' => 'msg.view.invalid_parent'], 400);
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
        return new JsonResponse(['message' => 'msg.view.view_name_exists'], 400);
      }

      if ($view->isBuiltIn()) {
        // 系统内置视图：跳过文件创建，使用预设的模板
        $view->setPath(null);
      } else {
        // 构建视图文件路径
        $basePath = $this->getParameter('kernel.project_dir') . '/templates/views';
        $name = $view->getName();

        // 磁盘文件夹名追加随机后缀，避免软删重建后同名视图的磁盘文件冲突。
        // path 存视图目录基路径（不含版本段），文件定位由 ViewPathResolver 按 path/current_version 解析。
        $diskName = $name . '_' . substr(bin2hex(random_bytes(4)), 0, 6);
        $relativePath = $this->buildRelativePath($parent, $diskName);

        // 创建视图目录结构：视图名/1_0/
        $viewFolderPath = $basePath . '/' . $relativePath;
        // 初始版本目录 1_0 表示 v1.0
        $versionFolderPath = $viewFolderPath . '/1_0';
        
        // 确保视图目录存在
        if (!file_exists($viewFolderPath)) {
          if (!mkdir($viewFolderPath, 0755, true)) {
            return new JsonResponse(['message' => 'msg.view.dir_create_failed'], 500);
          }
        }
        
        // 创建版本目录
        if (!file_exists($versionFolderPath)) {
          if (!mkdir($versionFolderPath, 0755, true)) {
            return new JsonResponse(['message' => 'msg.view.version_dir_failed'], 500);
          }
        }
        
        // 创建两个视图文件：1_0/name.html.twig 和 1_0/name.design.twig
        $htmlTwigPath = $versionFolderPath . '/' . $name . '.html.twig';
        $designTwigPath = $versionFolderPath . '/' . $name . '.design.twig';
        
        // 随机后缀已规避同名冲突，此处仅作兜底
        if (file_exists($htmlTwigPath) || file_exists($designTwigPath)) {
          return new JsonResponse(['message' => 'msg.view.view_fs_exists'], 400);
        }
        
        // 创建视图文件
        if (file_put_contents($htmlTwigPath, '{# ' . $view->getLabel() . ' 视图模板 #}\n{% extends "base.html.twig" %}\n\n{% block body %}\n  {# 视图内容 #}\n{% endblock %}') === false) {
          return new JsonResponse(['message' => 'msg.view.html_create_failed'], 500);
        }
        
        if (file_put_contents($designTwigPath, '') === false) {
          // 如果设计文件创建失败，删除已创建的HTML文件
          if (file_exists($htmlTwigPath)) {
            unlink($htmlTwigPath);
          }
          return new JsonResponse(['message' => 'msg.view.design_create_failed'], 500);
        }
        
        // 视图目录基路径入库（不含版本段），当前版本 1_0
        $view->setPath($relativePath);
        $view->setCurrentVersion('1_0');

        // 初始版本记录
        $initialVersion = new \App\Entity\Platform\ViewVersion();
        $initialVersion->setView($view);
        $initialVersion->setVersion('1_0');
        $initialVersion->setLabel('v1.0 初始版本');
        $initialVersion->setIsCurrent(true);
        $view->addVersion($initialVersion);
      }
      
      $em->persist($view);
      $em->flush();

      // AI 二次加工：若填写了 AI 组件需求，创建异步任务并派发消息
      $aiRequirement = trim((string) ($request->request->get('ai_requirement') ?? ''));
      if ($aiRequirement !== '') {
        $task = new AiViewEnhanceTask();
        $task->setView($view);
        $task->setRequirement($aiRequirement);
        $task->setCreatedBy($this->getUser()?->getUserIdentifier());
        $em->persist($task);
        $em->flush();

        $bus->dispatch(new EnhanceViewMessage(
          (string) $view->getId(),
          (string) $task->getId(),
          $aiRequirement,
        ));

        return new JsonResponse([
          'code' => 200,
          'data' => [
            'viewId' => (string) $view->getId(),
            'taskId' => (string) $task->getId(),
          ],
        ]);
      }

      $this->addFlash('success', 'flash.view_created');
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
      return new JsonResponse(['message' => 'msg.view.id_required'], 400);
    }

    $view = $em->getRepository(View::class)->find($viewId);
    if (!$view) {
      return new JsonResponse(['message' => 'msg.view.not_found'], 404);
    }

    $showBuiltIn = $this->isGranted('ROLE_SYS_ADMIN');
    $form = $this->createForm(ViewEditType::class, $view, [
      'action' => $this->generateUrl('platform_view_edit_view', ['id' => $viewId]),
      'show_built_in' => $showBuiltIn,
    ]);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
      $em->flush();
      $this->addFlash('success', 'flash.view_updated');
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
      return new JsonResponse(['message' => 'msg.view.folder_id_required'], 400);
    }

    $folder = $em->getRepository(View::class)->find($folderId);
    if (!$folder || $folder->getType() !== 'folder') {
      return new JsonResponse(['message' => 'msg.view.folder_not_found'], 404);
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
        return new JsonResponse(['message' => 'msg.view.folder_name_exists'], 400);
      }

      $em->flush();
      $this->addFlash('success', 'flash.folder_renamed');

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
    \Symfony\Component\HttpFoundation\Request $request,
    \App\Service\Form\FormFieldRenderer $formFieldRenderer,
    \App\Service\Form\FormLayoutService $formLayoutService,
    \Doctrine\ORM\EntityManagerInterface $em,
    \App\Service\Platform\View\ViewPathResolver $pathResolver,
    \Twig\Environment $twig,
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

    $tplVars = ['components' => $components, 'id' => $id];

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
      $tplVars['viewName'] = $view->getName();
      $tplVars['viewPath'] = $view->getPath();

      // 版本解析：?version= 显式指定，否则当前激活版本
      $requestedVersion = $request->query->get('version');
      $activeVersion = $requestedVersion && \App\Service\Platform\View\VersionNumber::isValid($requestedVersion)
        ? $requestedVersion
        : $pathResolver->currentVersion($view);
      $tplVars['activeVersion'] = $activeVersion;
      $tplVars['currentVersion'] = $pathResolver->currentVersion($view);
      $versions = [];
      foreach ($view->getVersions() as $vv) {
        if ($vv->isCurrent() && !$vv->getDeletedAt()) {
          $view->setCurrentVersion($vv->getVersion());
        }
        $versions[] = [
          'version' => $vv->getVersion(),
          'label' => $vv->getLabel(),
          'isCurrent' => $vv->isCurrent(),
        ];
      }
      $tplVars['viewVersions'] = $versions;

      // 尝试加载对应版本的设计文件（.design.twig），编辑状态以设计文件为准
      $designFilePath = $pathResolver->designFile($view, $activeVersion);

      if ($designFilePath && file_exists($designFilePath)) {
        $content = file_get_contents($designFilePath);
        if ($content !== false && trim($content) !== '') {
          // 剥离保存时固化的 section-controls（由 JS 动态添加，不应固化在 design 中）
          $content = preg_replace('/<div\s+class="[^"]*section-controls[^"]*"[^>]*>.*?<\/div>\s*/s', '', $content);
          // 剥离外层 section 结构（设计文件包含完整的 canvas HTML: add-section-button + section 包裹器），
          // 只取 .section-content 内部的内容；编辑器模板自身已生成 section 包裹，避免层层嵌套
          $content = $this->extractSectionContentInnerHtml($content);
          // 自定义 Twig 表单设计：画布需用 dummy form 渲染（避免 {{ form_widget(...) }} 显示为字面文本），
          // 并注入透明字段标记，让右侧"组件"面板可点击控件调整
          if (preg_match('/\{(form_start|form_end|form_rest|form_widget|form_label|form_errors|form_row)\}|\{\{\s*(form\b|form_)|form_start\(|form_end\(|form_widget\(|form_label\(|form_errors\(/', $content)) {
            try {
              $fqn = $view->getFormEntity()?->getFqn();
              $data = ($fqn && class_exists($fqn)) ? new $fqn() : (object) [];
              $built = $formFieldRenderer->build($view, $data, $tplVars['generalConfig'] ?? []);
              $content = $formLayoutService->renderDesignFragment($content, $built['formView'], $data, $built['fields'], true);
            } catch (\Throwable $e) {
              // 渲染失败时保留原始（展示 Twig 源码，供用户修正）
            }
          }
          $tplVars['initialCanvasHtml'] = $content;
        }
      }

      // 供 AI 助手使用的视图文件信息
      $tplVars['designFilePath'] = $designFilePath;
      $tplVars['viewDesignPath'] = $designFilePath ? str_replace($projectDir . '/templates/', '', $designFilePath) : null;
    }
    // 数据源面板：只要视图绑定了模型，就传入 formEntity + entityProperties，
    // 供左侧"数据源"标签展示（与是否有已保存的设计文件无关，避免误显示"暂未绑定模型"）
    if ($view && $view->getFormEntity()) {
      $tplVars['formEntity'] = $view->getFormEntity();
      $tplVars['entityProperties'] = $em->getRepository(\App\Entity\Platform\EntityProperty::class)
        ->findBy(['entity' => $view->getFormEntity()], ['orderNum' => 'ASC']);
    }

    // 内置表单视图且尚无已保存设计文件时，用绑定字段渲染初始画布
    if ($view && $view->getFormEntity() && $view->isBuiltIn() && !isset($tplVars['initialCanvasHtml'])) {
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

  /**
   * 版本预览（iframe）：渲染指定版本设计文件内容（.section-content 内部 HTML），
   * 用于版本切换前的可视化确认。
   */
  #[Route(
    '/admin/platform/view/{id}/versions/{version}/preview',
    name: 'platform_view_version_preview'
  )]
  public function versionPreview(
    string $id,
    string $version,
    \Doctrine\ORM\EntityManagerInterface $em,
    \App\Service\Platform\View\ViewPathResolver $pathResolver,
    \App\Service\Form\FormFieldRenderer $formFieldRenderer,
    \App\Service\Form\FormLayoutService $formLayoutService
  ): Response {
    $view = $em->getRepository(\App\Entity\Platform\View::class)->find($id);
    if (!$view || $view->getType() !== 'view' || !\App\Service\Platform\View\VersionNumber::isValid($version)) {
      throw $this->createNotFoundException('视图或版本不存在');
    }

    $designFile = $pathResolver->designFile($view, $version);
    $content = '';
    $found = false;
    if ($designFile && file_exists($designFile)) {
      $raw = file_get_contents($designFile);
      if ($raw !== false && trim($raw) !== '') {
        $content = $this->extractSectionContentInnerHtml($raw);
        // 自定义 Twig 表单设计：用 dummy form + 标准主题渲染预览
        if (preg_match('/\{(form_start|form_end|form_rest|form_widget|form_label|form_errors|form_row)\}|\{\{\s*(form\b|form_)|form_start\(|form_end\(|form_widget\(|form_label\(|form_errors\(/', $content)) {
          try {
            $fqn = $view->getFormEntity()?->getFqn();
            $data = ($fqn && class_exists($fqn)) ? new $fqn() : (object) [];
            $built = $formFieldRenderer->build($view, $data, []);
            $content = $formLayoutService->renderDesignFragment($content, $built['formView'], $data);
          } catch (\Throwable) {
            // 渲染失败保留原文
          }
        }
        $found = true;
      }
    }

    return $this->render('admin/platform/view/version_preview.html.twig', [
      'viewName' => $view->getLabel() ?: $view->getName(),
      'version' => $version,
      'content' => $content,
      'found' => $found,
    ]);
  }

  /**
   * 从包含完整 canvas 结构的 HTML 中提取 .section-content 的内部内容。
   * 设计文件保存的是完整 canvas HTML（含 add-section-button、section 包裹器等），
   * 但编辑器模板自身已生成 section 包裹，此处剥离外层只保留实际内容，避免层层嵌套。
   */
  private function extractSectionContentInnerHtml(string $html): string
  {
    $dom = new \DOMDocument();
    $useErrors = libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
    libxml_use_internal_errors($useErrors);
    $xpath = new \DOMXPath($dom);
    $nodes = $xpath->query("//*[contains(@class, 'section-content')]");    if ($nodes && $nodes->length > 0) {
      $inner = '';
      foreach ($nodes->item(0)->childNodes as $child) {
        $inner .= $dom->saveHTML($child);
      }
      return $inner;
    }
    return $html;
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
      'id' => $id,
    ]);
  }
}
