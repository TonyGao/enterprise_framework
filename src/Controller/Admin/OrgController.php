<?php

namespace App\Controller\Admin;

use Symfony\Component\Uid\Uuid;
use App\Controller\BaseController;
use App\Entity\Organization\Company;
use App\Form\Organization\CompanyType;
use App\Entity\Organization\Department;
use App\Entity\Organization\Corporation;
use App\Entity\Organization\Position;
use App\Entity\Organization\PositionLevel;
use App\Entity\Platform\Entity;
use Doctrine\ORM\EntityManagerInterface;
use App\Form\Organization\OrgDepartmentType;
use App\Form\Organization\PositionLevelType;
use App\Form\Organization\PositionType;
use Symfony\Component\HttpFoundation\Request;
use App\Form\Organization\CorporationFormType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Controller\Api\ApiResponse;
use App\Service\Platform\DataGridService;
use Symfony\Component\Security\Http\Attribute\IsGranted;
/**
 * 组织架构管理
 */
#[IsGranted('ROLE_ADMIN')]
class OrgController extends BaseController
{

  /**
   * 公司架构首页
   */
  #[Route('/admin/org/corporation', name: 'org_corporation')]
  public function corporation(Request $request, EntityManagerInterface $em): Response
  {
    $repo = $em->getRepository(Corporation::class);
    $corporation = null;
    $corporationArr = $repo->findAll();
    if ($corporationArr !== []) {
      $corporation = $corporationArr[0];
    }

    $comRepo = $em->getRepository(Company::class);
    $comTree = $comRepo->childrenHierarchy();

    return $this->render('admin/org/corporation.html.twig', [
      'corporation' => $corporation,
      'companies' => $comTree !== [] ? $comTree[0]['__children'] : null,
    ]);
  }

  /**
   * 集团编辑页面
   */
  #[Route('/admin/org/corporation/edit', name: 'org_corporation_edit')]
  public function createCorporation(
    Request $request,
    EntityManagerInterface $em,
    \App\Service\Form\FormFieldRenderer $formFieldRenderer
  ): Response {
    $repo = $em->getRepository(Corporation::class);
    $corporationArr = $repo->findAll();
    $corporation = new Corporation();
    $isFirstTime = true;
    if ($corporationArr !== []) {
      $corporation = $corporationArr[0];
      $isFirstTime = false;
    }

    // 检查是否存在内置视图且有 ViewField 配置
    $view = $em->getRepository(\App\Entity\Platform\View::class)
      ->findOneBy(['name' => 'company_structure_form', 'builtIn' => true]);
    $viewWithFields = $view && $view->getFormEntity()
      && $em->getRepository(\App\Entity\Platform\ViewField::class)
        ->count(['view' => $view]) > 0;

    if ($viewWithFields) {
      try {
        $generalConfig = [];
        $configFile = $this->getParameter('kernel.project_dir') . '/var/data/view_editor_config.json';
        if (file_exists($configFile)) {
          $json = file_get_contents($configFile);
          $generalConfig = json_decode($json, true) ?? [];
        }
        $result = $formFieldRenderer->render($view, $corporation, false, [], $generalConfig);
        $form = $result['form'];
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
          $corporation = $form->getData();
          $em->persist($corporation);
          $em->flush();

          if ($isFirstTime) {
            $company = new Company();
            $department = new Department();
          }
          if (!$isFirstTime) {
            $repo = $em->getRepository(Company::class);
            $company = $repo->findOneBy(['lvl' => 0]);
            $depRepo = $em->getRepository(Department::class);
            $department = $depRepo->findOneBy(['lvl' => 0]);
          }
          $company->setName($corporation->getName());
          if ($corporation->getAlias() != null) {
            $company->setAlias($corporation->getAlias());
          }
          $em->persist($company);
          $em->flush();
          $department->setName($corporation->getName())
            ->setType('corperations')
            ->setPath($corporation->getName());
          if ($corporation->getAlias() != null) {
            $department->setAlias($corporation->getAlias());
          }
          $em->persist($department);
          $em->flush();
          return $this->redirectToRoute('org_corporation');
        }

        $tplVars = [
          'form' => $result['formView'],
          'dynamicFormHtml' => $result['html'],
          'designerViewId' => $view->getId(),
          'designerViewLabel' => $view->getLabel() ?: $view->getName(),
          'designerViewEntityId' => $view->getFormEntity()?->getId(),
        ];
        return $this->render('admin/org/corporationEdit.html.twig', $tplVars);
      } catch (\Exception $e) {
        // fallback to default form
      }
    }

    $form = $this->createForm(CorporationFormType::class, $corporation);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
      $corporation = $form->getData();
      $em->persist($corporation);
      $em->flush();

      if ($isFirstTime) {
        $company = new Company();
        $department = new Department();
      }
      if (!$isFirstTime) {
        $repo = $em->getRepository(Company::class);
        $company = $repo->findOneBy(['lvl' => 0]);
        $depRepo = $em->getRepository(Department::class);
        $department = $depRepo->findOneBy(['lvl' => 0]);
      }
      $company->setName($corporation->getName());
      if ($corporation->getAlias() != null) {
        $company->setAlias($corporation->getAlias());
      }
      $em->persist($company);
      $em->flush();
      $department->setName($corporation->getName())
        ->setType('corperations')
        ->setPath($corporation->getName());
      if ($corporation->getAlias() != null) {
        $department->setAlias($corporation->getAlias());
      }
      $em->persist($department);
      $em->flush();

      return $this->redirectToRoute('org_corporation');
    }

    $tplVars = ['form' => $form->createView()];

    return $this->render('admin/org/corporationEdit.html.twig', $tplVars);
  }

  /**
   * 公司编辑页面
   */
  #[Route('/admin/org/company/edit/{id}', name: 'org_company_edit')]
  public function editCompany(
    Request $request,
    EntityManagerInterface $em,
    string $id,
    \App\Service\Form\FormFieldRenderer $formFieldRenderer,
    \App\Service\Form\FormLayoutService $formLayoutService
  ): Response {
    // Pre-load all companies before loading the specific entity. This ensures
    // Company's self-referencing ManyToOne associations (parent) reuse
    // existing entities from the identity map instead of creating proxies,
    // avoiding "Entity must be managed" errors from EntityType's IdReader.
    // The EntityType choice loader would run the same findAll() anyway.
    $em->getRepository(Company::class)->findAll();

    $repo = $em->getRepository(Company::class);
    $company = $repo->findOneBy(['id' => $id]);
    $oldName = $company->getName();

    $view = $em->getRepository(\App\Entity\Platform\View::class)
      ->findOneBy(['name' => 'company_edit_form', 'builtIn' => true]);

    $viewWithFields = $view && $view->getFormEntity()
      && $em->getRepository(\App\Entity\Platform\ViewField::class)
        ->count(['view' => $view]) > 0;

    if ($viewWithFields) {
      try {
        $generalConfig = [];
        $configFile = $this->getParameter('kernel.project_dir') . '/var/data/view_editor_config.json';
        if (file_exists($configFile)) {
          $json = file_get_contents($configFile);
          $generalConfig = json_decode($json, true) ?? [];
        }
        $result = $formFieldRenderer->build($view, $company, $generalConfig);
        $form = $result['form'];
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
          $submitCompany = $form->getData();
          $em->persist($submitCompany);
          $em->flush();

          $depRepo = $em->getRepository(Department::class);
          $department = $depRepo->findOneBy(['name' => $oldName]);
          if ($department) {
            $department->setName($submitCompany->getName())
              ->setAlias($submitCompany->getAlias());
            $em->persist($department);
            $em->flush();
          }
          return $this->redirectToRoute('org_corporation');
        }

        // 自定义 Twig 表单设计优先（AI 文件重写产物）；否则回退传统 ef-form 渲染
        $dynamicFormHtml = $formLayoutService->renderCustomDesign($view, $result['formView'], $company)
          ?? $formFieldRenderer->render($view, $company, false, [], $generalConfig)['html'];

        $tplVars = [
          'form' => $result['formView'],
          'dynamicFormHtml' => $dynamicFormHtml,
          'designerViewId' => $view->getId(),
          'designerViewLabel' => $view->getLabel() ?: $view->getName(),
          'designerViewEntityId' => $view->getFormEntity() ? $view->getFormEntity()->getId() : null,
        ];
        return $this->render('admin/org/companyEdit.html.twig', $tplVars);
      } catch (\Exception $e) {
        // fallback to default form
      }
    }

    $form = $this->createForm(CompanyType::class, $company, [
      'rounded' => true,
    ]);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
      $submitCompany = $form->getData();
      $em->persist($submitCompany);
      $em->flush();

      $depRepo = $em->getRepository(Department::class);
      $department = $depRepo->findOneBy(['name' => $oldName]);
      if ($department) {
        $department->setName($submitCompany->getName())
          ->setAlias($submitCompany->getAlias());
        $em->persist($department);
        $em->flush();
      }
      return $this->redirectToRoute('org_corporation');
    }

    $tplVars = ['form' => $form->createView()];

    if ($view) {
      $tplVars['designerViewId'] = $view->getId();
      $tplVars['designerViewLabel'] = $view->getLabel() ?: $view->getName();
      $tplVars['designerViewEntityId'] = $view->getFormEntity() ? $view->getFormEntity()->getId() : null;
    }

    return $this->render('admin/org/companyEdit.html.twig', $tplVars);
  }

  /**
   * 组织架构-部门管理
   */
  #[Route('/admin/org/department', name: 'org_department')]
  public function department(Request $request, EntityManagerInterface $em): Response
  {
    $repo = $em->getRepository(Department::class);
    $department = $repo->childrenHierarchy(null, false, [
      'decorate' => true,
      'rootOpen' => static function (array $tree): ?string {
        if ([] !== $tree && 0 == $tree[0]['lvl']) {
          return '<ol class="ol-left-tree">';
        }

        if ($tree[0]['type'] === 'department') {
          return '<span class="tree-indent" style="display: none;"></span><ol class="sub-tree-content" style="display: none;">';
        }

        return '<span class="tree-indent"></span><ol class="sub-tree-content">';
      },
      'rootClose' => static function (array $child): ?string {
        // if ([] !== $child && 0 == $child[0]['lvl']) {
        //   return '</ol>';
        // }

        return '</ol>';
      },
      'childOpen' => '<li>',
      'childClose' => '</li>',
      'nodeDecorator' => static function (array $node) use (&$controller) {
        if ($node['type'] === 'corperations') {
          return '
          <div class="item-content scroll-item">
            <div class="arrow-icon">
              <i class="fa-solid fa-caret-down"></i>
            </div>
						<div class="org-icon">
              <i class="fa-solid fa-building"></i>
						</div>
						<div class="org-name">
							<div class="org-text-content">' .
            $node['name']
            . '</div>
						</div>
					</div>
          ';
        }

        if ($node['type'] === 'company') {
          $arrayIcon = !empty($node['__children']) ? '<i class="fa-solid fa-caret-right"></i>' : '';

          return '
          <div class="item-content scroll-item">
            <div class="arrow-icon">' . $arrayIcon . '</div>
						<div class="org-icon">
              <i class="fa-solid fa-building-user"></i>
						</div>
						<div class="org-name">
							<div class="org-text-content company" type="company" id="' . $node['id'] . '">' .
            $node['name']
            . '</div>
						</div>
					</div>
          ';
        }

        if ($node['type'] === 'department') {
          $arrayIcon = !empty($node['__children']) ? '<i class="fa-solid fa-caret-right"></i>' : '';

          return '
          <div class="item-content scroll-item">
            <div class="arrow-icon">' . $arrayIcon . '</div>
						<div class="org-icon">
              <i class="fa-solid fa-user-group"></i>
						</div>
						<div class="org-name">
							<div class="org-text-content department" type="department" path="' . $node['path'] . '" id="' . $node['id'] . '">' .
            $node['name']
            . '</div>
						</div>
					</div>
          ';
        }
      }
    ]);
    return $this->render('admin/org/department.html.twig', [
      'departmentTree' => $department
    ]);
  }

  // private function buildDepartmentPath(array $node, array $repo): string
  // {
  //   // 递归构建路径，如果有父节点则加上父节点的路径
  //   if (!empty($node['parent'])) {
  //     $parentNode = $repo->find($node['parent']['id']);
  //     return $this->buildDepartmentPath($parentNode, $repo) . '/' . $node['name'];
  //   }

  //   // 如果没有父节点，则返回当前节点名称
  //   return $node['name'];
  // }

  /**
   * 组织架构-部门选择器（单部门选择）
   */
  #[Route('/admin/org/department/singleSelect', name: 'org_department_single_select', methods: ['GET'])]
  #[Route('/admin/org/departemnt/singleSelect', name: 'org_deparment_single_select', methods: ['GET'])]
  public function singleSelectDepartment(Request $request, EntityManagerInterface $em): Response
  {
    $repo = $em->getRepository(Department::class);
    $companyId = $request->query->get('companyId');
    $departmentInputId = Uuid::v1();

    $rootDepartment = null;
    if ($companyId) {
      $rootDepartment = $repo->findOneBy([
        'company' => $companyId,
        'type' => 'company'
      ]);

      // Some data sets store company root by node id only.
      if (!$rootDepartment) {
        $rootDepartment = $repo->findOneBy([
          'id' => $companyId,
          'type' => 'company'
        ]);
      }
    }

    $departmentSingleTree = '';
    if (!$companyId || $rootDepartment) {
      $departmentSingleTree = $repo->childrenHierarchy($rootDepartment, false, [
        'decorate' => true,
        'rootOpen' => static function (array $tree): ?string {
          static $openCount = 0;
          $openCount++;

          if ($openCount === 1) {
            return '<ol class="ol-left-tree">';
          }

          if ($tree[0]['type'] === 'department') {
            return '<span class="tree-indent" style="display: none;"></span><ol class="sub-tree-content" style="display: none;">';
          }

          return '<span class="tree-indent"></span><ol class="sub-tree-content">';
        },
        'rootClose' => static function (array $child): ?string {
          return '</ol>';
        },
        'childOpen' => '<li>',
        'childClose' => '</li>',
        'nodeDecorator' => static function (array $node) use ($departmentInputId) {
          if ($node['type'] === 'corperations') {
            return '
          <div class="item-content scroll-item">
            <div class="arrow-icon">
              <i class="fa-solid fa-caret-down"></i>
            </div>
            <div class="org-icon">
              <i class="fa-solid fa-building"></i>
            </div>
            <div class="org-name">
              <div class="org-text-content">' .
              $node['name']
              . '</div>
            </div>
          </div>
          ';
          }

          if ($node['type'] === 'company') {
            $arrayIcon = !empty($node['__children']) ? '<i class="fa-solid fa-caret-right"></i>' : '';

            return '
          <div class="item-content scroll-item">
            <div class="arrow-icon">' . $arrayIcon . '</div>
            <div class="org-icon">
              <i class="fa-solid fa-building-user"></i>
            </div>
            <div class="org-name">
              <div class="org-text-content company" type="company">' .
              $node['name']
              . '</div>
            </div>
          </div>
          ';
          }

          if ($node['type'] === 'department') {
            $arrayIcon = !empty($node['__children']) ? '<i class="fa-solid fa-caret-right"></i>' : '';
            return '
          <div class="item-content scroll-item">
            <div class="arrow-icon">' . $arrayIcon . '</div>
            <span class="department-select-line">
              <label class="ef-radio" style="padding-right: 5px;" radioId="' . $departmentInputId . '">
                <input type="radio" class="ef-radio-target" value="A">
                <span class="ef-icon-hover ef-radio-icon-hover">
                  <span class="ef-radio-icon"></span>
                </span>
              </label>
              <div class="org-icon">
                <i class="fa-solid fa-user-group"></i>
              </div>
              <div class="org-name">
                <div class="org-text-content department" type="department" path="' . $node['path'] . '" id="' . $node['id'] . '">' .
              $node['name']
              . '</div>
              </div>
            </span>
          </div>
          ';
          }

          return '';
        }
      ]);
    }

    return $this->render('admin/org/department/singleSelect.html.twig', [
      'departmentSingleTree' => $departmentSingleTree
    ]);
  }

  /**
   * 新建部门表单
   */
  #[Route('/admin/org/department/new', name: 'org_department_new')]
  public function createDepartment(
    Request $request,
    EntityManagerInterface $em,
    \App\Service\Form\FormFieldRenderer $formFieldRenderer
  ): Response {
    $parentId = $request->query->get('parent');

    $department = new Department();

    if ($parentId) {
      $parentDepartment = $em->getRepository(Department::class)->find($parentId);
      if ($parentDepartment) {
        if ($parentDepartment->getType() === 'department') {
          $department->setParent($parentDepartment);
        }
        $department->setCompany($parentDepartment->getCompany());
      }
    }

    // 查找视图设计器
    $view = $em->getRepository(\App\Entity\Platform\View::class)
      ->findOneBy(['name' => 'department_edit_form', 'builtIn' => true]);

    $viewWithFields = $view && $view->getFormEntity()
      && $em->getRepository(\App\Entity\Platform\ViewField::class)
        ->count(['view' => $view]) > 0;

    if ($viewWithFields) {
      try {
        $generalConfig = [];
        $configFile = $this->getParameter('kernel.project_dir') . '/var/data/view_editor_config.json';
        if (file_exists($configFile)) {
          $json = file_get_contents($configFile);
          $generalConfig = json_decode($json, true) ?? [];
        }
        $result = $formFieldRenderer->render($view, $department, false, [
          'action' => $this->generateUrl('org_department_new'),
        ], $generalConfig);
        $form = $result['form'];
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
          $departmentPost = $form->getData();
          if ($departmentPost->getType() == 'department' && $departmentPost->getParent() == null) {
            $company = $departmentPost->getCompany()->getName();
            $repo = $em->getRepository(Department::class);
            $parent = $repo->findOneBy(['name' => $company]);
            $departmentPost->setParent($parent);
          }

          $pathComponents = [];
          if ($departmentPost->getParent()) {
            $pathComponents[] = $departmentPost->getParent()->getPath();
          }
          $pathComponents[] = $departmentPost->getName();
          $departmentPost->setPath(implode('/', $pathComponents));

          $em->persist($departmentPost);
          $em->flush();

          if ($request->isXmlHttpRequest()) {
            $parentId = $departmentPost->getParent()
              ? $departmentPost->getParent()->getId()
              : ($departmentPost->getCompany() ? $departmentPost->getCompany()->getId() : null);

            $html = $this->renderView('admin/org/department_node.html.twig', [
              'name' => $departmentPost->getName(),
              'path' => $departmentPost->getPath(),
              'id' => $departmentPost->getId()
            ]);

            return ApiResponse::success([
              'type' => 'new',
              'parentId' => $parentId,
              'html' => $html
            ], 200, '部门创建成功');
          }

          $this->addFlash('org.singleDepartment', 'clear');
          return $this->redirectToRoute('org_department');
        }

        return $this->render('admin/org/departmentEdit.html.twig', [
          'form' => $result['formView'],
          'dynamicFormHtml' => $result['html'],
          'designerViewId' => $view->getId(),
          'designerViewLabel' => $view->getLabel() ?: $view->getName(),
          'designerViewEntityId' => $view->getFormEntity()?->getId(),
        ]);
      } catch (\Exception $e) {
        // fallback to default form below
      }
    }

    $form = $this->createForm(OrgDepartmentType::class, $department, [
      'action' => $this->generateUrl('org_department_new')
    ]);

    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
      $departmentPost = $form->getData();
      if ($departmentPost->getType() == 'department' && $departmentPost->getParent() == null) {
        $company = $departmentPost->getCompany()->getName();
        $repo = $em->getRepository(Department::class);
        $parent = $repo->findOneBy(['name' => $company]);
        $departmentPost->setParent($parent);
      }

      $pathComponents = [];
      if ($departmentPost->getParent()) {
        $pathComponents[] = $departmentPost->getParent()->getPath();
      }
      $pathComponents[] = $departmentPost->getName();
      $departmentPost->setPath(implode('/', $pathComponents));

      $em->persist($departmentPost);
      $em->flush();

      if ($request->isXmlHttpRequest()) {
          $parentId = $departmentPost->getParent() ? $departmentPost->getParent()->getId() : ($departmentPost->getCompany() ? $departmentPost->getCompany()->getId() : null);

          $html = $this->renderView('admin/org/department_node.html.twig', [
              'name' => $departmentPost->getName(),
              'path' => $departmentPost->getPath(),
              'id' => $departmentPost->getId()
          ]);

          return ApiResponse::success([
              'type' => 'new',
              'parentId' => $parentId,
              'html' => $html
          ], 200, '部门创建成功');
      }

      $this->addFlash('org.singleDepartment', 'clear');
      return $this->redirectToRoute('org_department');
    }

    return $this->render('admin/org/departmentEdit.html.twig', [
      'form' => $form->createView(),
      'designerViewName' => 'department_edit_form',
      'designerViewLabel' => '部门表单',
      'designerEntityFqn' => \App\Entity\Organization\Department::class,
    ]);
  }

  /**
   * 返回部门表单
   */
  #[Route('/admin/org/department/edit/{id}', name: 'org_department_edit')]
  public function departmentForm(
    Request $request,
    EntityManagerInterface $em,
    Department $department,
    \App\Service\Form\FormFieldRenderer $formFieldRenderer
  ): Response {
    // 查找 department_edit_form 内置视图
    $view = $em->getRepository(\App\Entity\Platform\View::class)
      ->findOneBy(['name' => 'department_edit_form', 'builtIn' => true]);

    $viewWithFields = $view && $view->getFormEntity()
      && $em->getRepository(\App\Entity\Platform\ViewField::class)
        ->count(['view' => $view]) > 0;

    if ($viewWithFields) {
      try {
        $generalConfig = [];
        $configFile = $this->getParameter('kernel.project_dir') . '/var/data/view_editor_config.json';
        if (file_exists($configFile)) {
          $json = file_get_contents($configFile);
          $generalConfig = json_decode($json, true) ?? [];
        }
        $result = $formFieldRenderer->render($view, $department, false, [], $generalConfig);
        $form = $result['form'];
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
          $submitDepartment = $form->getData();
          $em->persist($submitDepartment);
          $em->flush();

          if ($request->isXmlHttpRequest()) {
            return ApiResponse::success([
              'type' => 'edit',
              'id' => $submitDepartment->getId(),
              'name' => $submitDepartment->getName()
            ], 200, '部门修改成功');
          }

          return $this->redirectToRoute('org_department');
        }

        return $this->render('admin/org/departmentEdit.html.twig', [
          'form' => $result['formView'],
          'dynamicFormHtml' => $result['html'],
          'designerViewId' => $view->getId(),
          'designerViewLabel' => $view->getLabel() ?: $view->getName(),
          'designerViewEntityId' => $view->getFormEntity()?->getId(),
        ]);
      } catch (\Exception $e) {
        // fallback to default form below
      }
    }

    $form = $this->createForm(OrgDepartmentType::class, $department, [
      'action' => $this->generateUrl('org_department_edit', ['id' => $department->getId()])
    ]);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
      $submitDepartment = $form->getData();
      $em->persist($submitDepartment);
      $em->flush();

      if ($request->isXmlHttpRequest()) {
          return ApiResponse::success([
              'type' => 'edit',
              'id' => $submitDepartment->getId(),
              'name' => $submitDepartment->getName()
          ], 200, '部门修改成功');
      }

      return $this->redirectToRoute('org_department');
    }

    return $this->render('admin/org/departmentEdit.html.twig', array_merge(
      ['form' => $form->createView()],
      $view ? [
        'designerViewId' => $view->getId(),
        'designerViewLabel' => $view->getLabel() ?: $view->getName(),
        'designerViewEntityId' => $view->getFormEntity()?->getId(),
      ] : [
        'designerViewName' => 'department_edit_form',
        'designerViewLabel' => '部门表单',
        'designerEntityFqn' => \App\Entity\Organization\Department::class,
      ]
    ));
  }





  /**
   * 岗位管理列表页
   */
  #[Route('/admin/org/position', name: 'org_position')]
  public function positionList(Request $request, EntityManagerInterface $em): Response
  {
    // 初始页面加载，只返回空数据和配置
    $positionLevels = $em->getRepository(PositionLevel::class)->findAll();

    // 定义表格列配置
    $columns = [
      ['field' => 'name', 'label' => '岗位名称'],
      ['field' => 'code', 'label' => '岗位编码'],
      ['field' => 'department', 'label' => '所属部门'],
      ['field' => 'level', 'label' => '岗位级别'],
      ['field' => 'parent', 'label' => '上级岗位'],
      ['field' => 'headcount', 'label' => '编制人数'],
      ['field' => 'state', 'label' => '状态'],
    ];

    $page = $request->query->getInt('page', 1);
    $pageSize = $request->query->getInt('pageSize', 20);

    return $this->render('admin/org/position/index.html.twig', [
      'tableData' => [], // 初始为空，通过AJAX加载
      'columns' => $columns,
      'positionLevels' => $positionLevels,
      'currentPage' => $page,
      'pageSize' => $pageSize
    ]);
  }

  /**
   * 新建岗位
   */
  #[Route('/admin/org/position/new', name: 'org_position_new')]
  public function createPosition(
    Request $request,
    EntityManagerInterface $em,
    DataGridService $dataGridService,
    \App\Service\Form\FormFieldRenderer $formFieldRenderer
  ): Response {
    $position = new Position();

    $departmentId = $request->query->get('department');
    if ($departmentId) {
      $department = $em->getRepository(Department::class)->find($departmentId);
      if ($department) {
        $position->setDepartment($department);
      }
    }

    $parentId = $request->query->get('parent');
    if ($parentId) {
      $parent = $em->getRepository(Position::class)->find($parentId);
      if ($parent) {
        $position->setParent($parent);
        if (!$position->getDepartment() && $parent->getDepartment()) {
          $position->setDepartment($parent->getDepartment());
        }
      }
    }

    // 查找视图设计器
    $view = $em->getRepository(\App\Entity\Platform\View::class)
      ->findOneBy(['name' => 'position_edit_form', 'builtIn' => true]);

    $viewWithFields = $view && $view->getFormEntity()
      && $em->getRepository(\App\Entity\Platform\ViewField::class)
        ->count(['view' => $view]) > 0;

    if ($viewWithFields) {
      try {
        $generalConfig = [];
        $configFile = $this->getParameter('kernel.project_dir') . '/var/data/view_editor_config.json';
        if (file_exists($configFile)) {
          $json = file_get_contents($configFile);
          $generalConfig = json_decode($json, true) ?? [];
        }
        $result = $formFieldRenderer->render($view, $position, false, [
          'action' => $this->generateUrl('org_position_new'),
        ], $generalConfig);
        $form = $result['form'];
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
          $position = $form->getData();
          $em->persist($position);
          $em->flush();
          $dataGridService->clearEntityCache(Position::class);

          if ($request->isXmlHttpRequest()) {
            return ApiResponse::success([], 200, '岗位创建成功');
          }

          $this->addFlash('success', '岗位创建成功');
          return $this->redirectToRoute('org_position');
        }

        return $this->render('admin/org/position/form.html.twig', [
          'form' => $result['formView'],
          'dynamicFormHtml' => $result['html'],
          'designerViewId' => $view->getId(),
          'designerViewLabel' => $view->getLabel() ?: $view->getName(),
          'title' => '新建岗位',
        ]);
      } catch (\Exception $e) {
        // fallback to default form below
      }
    }

    $form = $this->createForm(PositionType::class, $position, [
      'action' => $this->generateUrl('org_position_new')
    ]);

    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
      $position = $form->getData();
      $em->persist($position);
      $em->flush();
      $dataGridService->clearEntityCache(Position::class);

      $this->addFlash('success', '岗位创建成功');
      return $this->redirectToRoute('org_position');
    }

    return $this->render('admin/org/position/form.html.twig', [
      'form' => $form->createView(),
      'title' => '新建岗位'
    ]);
  }

  /**
   * 编辑岗位
   */
  #[Route('/admin/org/position/edit/{id}', name: 'org_position_edit')]
  public function editPosition(
    Request $request,
    EntityManagerInterface $em,
    string $id,
    DataGridService $dataGridService,
    \App\Service\Form\FormFieldRenderer $formFieldRenderer
  ): Response {
    $position = $em->getRepository(Position::class)->find($id);

    if (!$position) {
      throw $this->createNotFoundException('岗位不存在');
    }

    // 查找视图设计器
    $view = $em->getRepository(\App\Entity\Platform\View::class)
      ->findOneBy(['name' => 'position_edit_form', 'builtIn' => true]);

    $viewWithFields = $view && $view->getFormEntity()
      && $em->getRepository(\App\Entity\Platform\ViewField::class)
        ->count(['view' => $view]) > 0;

    if ($viewWithFields) {
      try {
        $generalConfig = [];
        $configFile = $this->getParameter('kernel.project_dir') . '/var/data/view_editor_config.json';
        if (file_exists($configFile)) {
          $json = file_get_contents($configFile);
          $generalConfig = json_decode($json, true) ?? [];
        }
        $result = $formFieldRenderer->render($view, $position, false, [
          'action' => $this->generateUrl('org_position_edit', ['id' => $id]),
        ], $generalConfig);
        $form = $result['form'];
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
          $position = $form->getData();
          $em->flush();
          $dataGridService->clearEntityCache(Position::class);

          if ($request->isXmlHttpRequest()) {
            return ApiResponse::success([], 200, '岗位更新成功');
          }

          $this->addFlash('success', '岗位更新成功');
          return $this->redirectToRoute('org_position');
        }

        return $this->render('admin/org/position/form.html.twig', [
          'form' => $result['formView'],
          'dynamicFormHtml' => $result['html'],
          'designerViewId' => $view->getId(),
          'designerViewLabel' => $view->getLabel() ?: $view->getName(),
          'title' => '编辑岗位',
          'position' => $position,
        ]);
      } catch (\Exception $e) {
        // fallback to default form below
      }
    }

    $form = $this->createForm(PositionType::class, $position, [
      'action' => $this->generateUrl('org_position_edit', ['id' => $id])
    ]);

    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
      $position = $form->getData();
      $em->flush();
      $dataGridService->clearEntityCache(Position::class);

      $this->addFlash('success', '岗位更新成功');
      return $this->redirectToRoute('org_position');
    }

    return $this->render('admin/org/position/form.html.twig', [
      'form' => $form->createView(),
      'title' => '编辑岗位',
      'position' => $position
    ]);
  }

  /**
   * 批量删除岗位
   */
  #[Route('/admin/org/position/batch-delete', name: 'org_position_batch_delete', methods: ['POST'])]
  public function batchDeletePosition(Request $request, EntityManagerInterface $em, DataGridService $dataGridService): Response
  {
      $data = $request->toArray();
      $ids = $data['ids'] ?? [];
      $isSelectAll = $data['isSelectAll'] ?? false;
      $excludedIds = $data['excludedIds'] ?? [];
      
      if (empty($ids) && !$isSelectAll) {
          return ApiResponse::error('', 400, '请选择要删除的岗位');
      }

      $repo = $em->getRepository(Position::class);
      $employeeRepo = $em->getRepository('App\Entity\Organization\Employee');
      
      if ($isSelectAll) {
          $qb = $repo->createQueryBuilder('p')->select('p.id');
          if (!empty($excludedIds)) {
              $qb->where($qb->expr()->notIn('p.id', $excludedIds));
          }
          $result = $qb->getQuery()->getScalarResult();
          $ids = array_column($result, 'id');
          
          if (empty($ids)) {
               return ApiResponse::error('', 400, '没有可删除的岗位');
          }
      }

      $deletedCount = 0;
      $errorCount = 0;
      $errors = [];

      foreach ($ids as $id) {
          $position = $repo->find($id);
          if (!$position) continue;

          // 检查是否有下级岗位
          $hasChildren = $repo->findBy(['parent' => $position]);
          if (count($hasChildren) > 0) {
              $errorCount++;
              $errors[] = "岗位 {$position->getName()} 存在下级岗位";
              continue;
          }

          // 检查是否有员工关联
          $hasEmployees = $employeeRepo->findBy(['position' => $position]);
          if (count($hasEmployees) > 0) {
              $errorCount++;
              $errors[] = "岗位 {$position->getName()} 已有员工关联";
              continue;
          }

          $position->setState(false);
          $em->persist($position);
          $deletedCount++;
      }

      if ($deletedCount > 0) {
          $em->flush();
          $dataGridService->clearEntityCache(Position::class);
      }

      if ($errorCount > 0) {
          $msg = "成功删除 {$deletedCount} 个岗位，{$errorCount} 个失败：" . implode('; ', $errors);
          return ApiResponse::success(json_encode(['deleted' => $deletedCount, 'errors' => $errors]), 200, $msg);
      }

      return ApiResponse::success('', 200, "成功删除 {$deletedCount} 个岗位");
  }

  /**
   * 删除岗位
   */
  #[Route('/admin/org/position/delete/{id}', name: 'org_position_delete', methods: ['POST'])]
  public function deletePosition(Request $request, EntityManagerInterface $em, string $id, DataGridService $dataGridService): Response
  {
    $position = $em->getRepository(Position::class)->find($id);

    if (!$position) {
      throw $this->createNotFoundException('岗位不存在');
    }

    // 检查是否有下级岗位
    $hasChildren = $em->getRepository(Position::class)->findBy(['parent' => $position]);
    if (count($hasChildren) > 0) {
      $this->addFlash('error', '该岗位存在下级岗位，无法删除');
      return $this->redirectToRoute('org_position');
    }

    // 检查是否有员工关联
    $hasEmployees = $em->getRepository('App\Entity\Organization\Employee')->findBy(['position' => $position]);
    if (count($hasEmployees) > 0) {
      $this->addFlash('error', '该岗位已有员工关联，无法删除');
      return $this->redirectToRoute('org_position');
    }

    $em->remove($position);
    $em->flush();
    $dataGridService->clearEntityCache(Position::class);

    $this->addFlash('success', '岗位删除成功');
    return $this->redirectToRoute('org_position');
  }

  /**
   * 岗位详情
   */
  #[Route('/admin/org/position/view/{id}', name: 'org_position_view')]
  public function viewPosition(Request $request, EntityManagerInterface $em, string $id): Response
  {
    $position = $em->getRepository(Position::class)->find($id);

    if (!$position) {
      throw $this->createNotFoundException('岗位不存在');
    }

    // 获取该岗位下的员工
    $employees = $em->getRepository('App\Entity\Organization\Employee')->findBy(['position' => $position]);

    return $this->render('admin/org/position/view.html.twig', [
      'position' => $position,
      'employees' => $employees
    ]);
  }

  /**
   * 岗位级别管理
   */
  #[Route('/admin/org/position/level', name: 'org_position_level')]
  public function positionLevelList(Request $request): Response
  {
    // 定义表格列配置
    $columns = [
      ['field' => 'name', 'label' => '级别名称'],
      ['field' => 'code', 'label' => '级别编码'],
      ['field' => 'levelOrder', 'label' => '级别序号'],
      ['field' => 'salaryMin', 'label' => '薪资下限'],
      ['field' => 'salaryMax', 'label' => '薪资上限'],
      ['field' => 'state', 'label' => '状态'],
    ];

    $page = $request->query->getInt('page', 1);
    $pageSize = $request->query->getInt('pageSize', 20);

    return $this->render('admin/org/position/level_index.html.twig', [
      'tableData' => [],
      'columns' => $columns,
      'currentPage' => $page,
      'pageSize' => $pageSize
    ]);
  }

  /**
   * 新建岗位级别
   */
  #[Route('/admin/org/position/level/new', name: 'org_position_level_new')]
  public function createPositionLevel(
    Request $request,
    EntityManagerInterface $em,
    DataGridService $dataGridService,
    \App\Service\Form\FormFieldRenderer $formFieldRenderer
  ): Response {
    $positionLevel = new PositionLevel();

    // 查找视图设计器
    $view = $em->getRepository(\App\Entity\Platform\View::class)
      ->findOneBy(['name' => 'position_level_edit_form', 'builtIn' => true]);

    $viewWithFields = $view && $view->getFormEntity()
      && $em->getRepository(\App\Entity\Platform\ViewField::class)
        ->count(['view' => $view]) > 0;

    if ($viewWithFields) {
      try {
        $generalConfig = [];
        $configFile = $this->getParameter('kernel.project_dir') . '/var/data/view_editor_config.json';
        if (file_exists($configFile)) {
          $json = file_get_contents($configFile);
          $generalConfig = json_decode($json, true) ?? [];
        }
        $result = $formFieldRenderer->render($view, $positionLevel, false, [
          'action' => $this->generateUrl('org_position_level_new'),
        ], $generalConfig);
        $form = $result['form'];
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
          $positionLevel = $form->getData();
          $em->persist($positionLevel);
          $em->flush();
          $dataGridService->clearEntityCache(PositionLevel::class);

          if ($request->isXmlHttpRequest()) {
            return ApiResponse::success([], 200, '岗位级别创建成功');
          }

          $this->addFlash('success', '岗位级别创建成功');
          return $this->redirectToRoute('org_position_level');
        }

        return $this->render('admin/org/position/level_form.html.twig', [
          'form' => $result['formView'],
          'dynamicFormHtml' => $result['html'],
          'designerViewId' => $view->getId(),
          'designerViewLabel' => $view->getLabel() ?: $view->getName(),
          'title' => '新建岗位级别',
        ]);
      } catch (\Exception $e) {
        // fallback to default form below
      }
    }

    $form = $this->createForm(PositionLevelType::class, $positionLevel, [
      'action' => $this->generateUrl('org_position_level_new')
    ]);

    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
      $positionLevel = $form->getData();
      $em->persist($positionLevel);
      $em->flush();
      $dataGridService->clearEntityCache(PositionLevel::class);

      $this->addFlash('success', '岗位级别创建成功');
      return $this->redirectToRoute('org_position_level');
    }

    return $this->render('admin/org/position/level_form.html.twig', [
      'form' => $form->createView(),
      'title' => '新建岗位级别'
    ]);
  }

  /**
   * 编辑岗位级别
   */
  #[Route('/admin/org/position/level/edit/{id}', name: 'org_position_level_edit')]
  public function editPositionLevel(
    Request $request,
    EntityManagerInterface $em,
    string $id,
    DataGridService $dataGridService,
    \App\Service\Form\FormFieldRenderer $formFieldRenderer
  ): Response {
    $positionLevel = $em->getRepository(PositionLevel::class)->find($id);

    if (!$positionLevel) {
      throw $this->createNotFoundException('岗位级别不存在');
    }

    // 查找视图设计器
    $view = $em->getRepository(\App\Entity\Platform\View::class)
      ->findOneBy(['name' => 'position_level_edit_form', 'builtIn' => true]);

    $viewWithFields = $view && $view->getFormEntity()
      && $em->getRepository(\App\Entity\Platform\ViewField::class)
        ->count(['view' => $view]) > 0;

    if ($viewWithFields) {
      try {
        $generalConfig = [];
        $configFile = $this->getParameter('kernel.project_dir') . '/var/data/view_editor_config.json';
        if (file_exists($configFile)) {
          $json = file_get_contents($configFile);
          $generalConfig = json_decode($json, true) ?? [];
        }
        $result = $formFieldRenderer->render($view, $positionLevel, false, [
          'action' => $this->generateUrl('org_position_level_edit', ['id' => $id]),
        ], $generalConfig);
        $form = $result['form'];
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
          $positionLevel = $form->getData();
          $em->flush();
          $dataGridService->clearEntityCache(PositionLevel::class);

          if ($request->isXmlHttpRequest()) {
            return ApiResponse::success([], 200, '岗位级别更新成功');
          }

          $this->addFlash('success', '岗位级别更新成功');
          return $this->redirectToRoute('org_position_level');
        }

        return $this->render('admin/org/position/level_form.html.twig', [
          'form' => $result['formView'],
          'dynamicFormHtml' => $result['html'],
          'designerViewId' => $view->getId(),
          'designerViewLabel' => $view->getLabel() ?: $view->getName(),
          'title' => '编辑岗位级别',
          'positionLevel' => $positionLevel,
        ]);
      } catch (\Exception $e) {
        // fallback to default form below
      }
    }

    $form = $this->createForm(PositionLevelType::class, $positionLevel, [
      'action' => $this->generateUrl('org_position_level_edit', ['id' => $id])
    ]);

    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
      $em->flush();
      $dataGridService->clearEntityCache(PositionLevel::class);
      $this->addFlash('success', '岗位级别更新成功');
      return $this->redirectToRoute('org_position_level');
    }

    return $this->render('admin/org/position/level_form.html.twig', [
      'form' => $form->createView(),
      'title' => '编辑岗位级别'
    ]);
  }

  /**
   * 返回岗位级别查看/编辑/新增的drawer HTML
   */
  #[Route('/admin/org/position/level/drawer', name: 'api_org_position_level_drawer', methods: ['POST'])]
  public function positionLevelDrawer(
    Request $request,
    EntityManagerInterface $em,
    \App\Service\Form\FormFieldRenderer $formFieldRenderer
  ): Response {
    $payload = $request->toArray();
    $levelId = $payload['levelId'] ?? null;
    $action = $payload['action'] ?? 'view'; // view、edit 或 create

    // 查找视图设计器
    $view = $em->getRepository(\App\Entity\Platform\View::class)
      ->findOneBy(['name' => 'position_level_edit_form', 'builtIn' => true]);

    $viewWithFields = $view && $view->getFormEntity()
      && $em->getRepository(\App\Entity\Platform\ViewField::class)
        ->count(['view' => $view]) > 0;

    $designerVariables = [];
    if ($view) {
      $designerVariables = [
        'designerViewId' => $view->getId(),
        'designerViewName' => 'position_level_edit_form',
        'designerViewLabel' => $view->getLabel() ?: $view->getName(),
        'designerViewEntityId' => $view->getFormEntity()?->getId(),
        'designerEntityFqn' => \App\Entity\Organization\PositionLevel::class,
      ];
    } elseif ($this->isGranted('ROLE_SYS_ADMIN')) {
      $designerVariables = [
        'designerViewName' => 'position_level_edit_form',
        'designerViewLabel' => '岗位级别表单',
        'designerEntityFqn' => \App\Entity\Organization\PositionLevel::class,
      ];
    }

    // 如果是创建操作
    if ($action === 'create') {
      $positionLevel = new PositionLevel();

      if ($viewWithFields) {
        try {
          $generalConfig = [];
          $configFile = $this->getParameter('kernel.project_dir') . '/var/data/view_editor_config.json';
          if (file_exists($configFile)) {
            $json = file_get_contents($configFile);
            $generalConfig = json_decode($json, true) ?? [];
          }
          $result = $formFieldRenderer->render($view, $positionLevel, false, [
            'action' => $this->generateUrl('org_position_level_new'),
          ], $generalConfig);

          return $this->render('admin/org/position/level_create_drawer.html.twig', array_merge([
            'positionLevel' => $positionLevel,
            'form' => $result['formView'],
            'dynamicFormHtml' => $result['html'],
            'drawerId' => 'position-level-drawer-new',
          ], $designerVariables));
        } catch (\Exception $e) {
          // fallback to standard form below
        }
      }

      $form = $this->createForm(PositionLevelType::class, $positionLevel, [
        'action' => $this->generateUrl('org_position_level_new')
      ]);

      return $this->render('admin/org/position/level_create_drawer.html.twig', array_merge([
        'positionLevel' => $positionLevel,
        'form' => $form->createView(),
        'drawerId' => 'position-level-drawer-new'
      ], $designerVariables));
    }

    if (!$levelId) {
      throw $this->createNotFoundException('岗位级别ID不能为空');
    }

    $positionLevel = $em->getRepository(PositionLevel::class)->find($levelId);

    if (!$positionLevel) {
      throw $this->createNotFoundException('岗位级别不存在');
    }

    if ($action === 'edit') {
      if ($viewWithFields) {
        try {
          $generalConfig = [];
          $configFile = $this->getParameter('kernel.project_dir') . '/var/data/view_editor_config.json';
          if (file_exists($configFile)) {
            $json = file_get_contents($configFile);
            $generalConfig = json_decode($json, true) ?? [];
          }
          $result = $formFieldRenderer->render($view, $positionLevel, false, [
            'action' => $this->generateUrl('org_position_level_edit', ['id' => $levelId]),
          ], $generalConfig);

          return $this->render('admin/org/position/level_edit_drawer.html.twig', array_merge([
            'positionLevel' => $positionLevel,
            'form' => $result['formView'],
            'dynamicFormHtml' => $result['html'],
            'drawerId' => 'position-level-drawer-' . $levelId,
          ], $designerVariables));
        } catch (\Exception $e) {
          // fallback to standard form below
        }
      }

      $form = $this->createForm(PositionLevelType::class, $positionLevel, [
        'action' => $this->generateUrl('org_position_level_edit', ['id' => $levelId])
      ]);

      return $this->render('admin/org/position/level_edit_drawer.html.twig', array_merge([
        'positionLevel' => $positionLevel,
        'form' => $form->createView(),
        'drawerId' => 'position-level-drawer-' . $levelId
      ], $designerVariables));
    }

    return $this->render('admin/org/position/level_view_drawer.html.twig', [
      'positionLevel' => $positionLevel,
      'drawerId' => 'position-level-drawer-' . $levelId
    ]);
  }

  /**
   * 用来返回岗位选择器弹窗的html
   */
  #[Route('/admin/org/position/modal', name: 'api_org_position_modal', methods: ['POST'])]
  public function positionModal(Request $request, EntityManagerInterface $em)
  {
    $payload = $request->toArray();
    $type = $payload['positionType'] ?? 'single';
    $positionInputId = $payload['positionInputId'] ?? Uuid::v1();

    $positions = $em->getRepository(Position::class)->findBy(['state' => true]);

    if ($type == 'single') {
      return $this->render('admin/org/position/position_modal.html.twig', [
        'positionInputId' => $positionInputId,
        'positions' => $positions
      ]);
    }

    return $this->render('admin/org/position/position_multi_modal.html.twig', [
      'positionInputId' => $positionInputId,
      'positions' => $positions
    ]);
  }

  /**
   * 用来返回部门弹窗的html
   */
  #[Route('/admin/org/department/modal', name: 'api_org_department_modal', methods: ['POST'])]
  public function departmentModal(Request $request, EntityManagerInterface $em)
  {
    $payload = $request->toArray();
    $type = $payload['departmentType'];
    $departmentInputId = $payload['departmentInputId'];

    if ($type == 'single') {
      return $this->render('admin/org/department/department_modal.html.twig', [
        'departmentInputId' => $departmentInputId
      ]);
    }
  }

  /**
   * 返回岗位查看/编辑/新增的drawer HTML
   */
  #[Route('/admin/org/position/drawer', name: 'api_org_position_drawer', methods: ['POST'])]
  public function positionDrawer(
    Request $request,
    EntityManagerInterface $em,
    \App\Service\Form\FormFieldRenderer $formFieldRenderer
  ): Response {
    $payload = $request->toArray();
    $positionId = $payload['positionId'] ?? null;
    $action = $payload['action'] ?? 'view'; // view、edit 或 create

    // 查找视图设计器
    $view = $em->getRepository(\App\Entity\Platform\View::class)
      ->findOneBy(['name' => 'position_edit_form', 'builtIn' => true]);

    $viewWithFields = $view && $view->getFormEntity()
      && $em->getRepository(\App\Entity\Platform\ViewField::class)
        ->count(['view' => $view]) > 0;

    $designerVariables = [];
    if ($view) {
      $designerVariables = [
        'designerViewId' => $view->getId(),
        'designerViewName' => 'position_edit_form',
        'designerViewLabel' => $view->getLabel() ?: $view->getName(),
        'designerViewEntityId' => $view->getFormEntity()?->getId(),
        'designerEntityFqn' => \App\Entity\Organization\Position::class,
      ];
    } elseif ($this->isGranted('ROLE_SYS_ADMIN')) {
      $designerVariables = [
        'designerViewName' => 'position_edit_form',
        'designerViewLabel' => '岗位表单',
        'designerEntityFqn' => \App\Entity\Organization\Position::class,
      ];
    }

    // 如果是创建操作，不需要positionId
    if ($action === 'create') {
      $position = new Position();

      if ($viewWithFields) {
        try {
          $generalConfig = [];
          $configFile = $this->getParameter('kernel.project_dir') . '/var/data/view_editor_config.json';
          if (file_exists($configFile)) {
            $json = file_get_contents($configFile);
            $generalConfig = json_decode($json, true) ?? [];
          }
          $result = $formFieldRenderer->render($view, $position, false, [
            'action' => $this->generateUrl('org_position_new'),
          ], $generalConfig);

          return $this->render('admin/org/position/create_drawer.html.twig', array_merge([
            'position' => $position,
            'form' => $result['formView'],
            'dynamicFormHtml' => $result['html'],
            'drawerId' => 'position-drawer-new',
          ], $designerVariables));
        } catch (\Exception $e) {
          // fallback to standard form below
        }
      }

      $form = $this->createForm(PositionType::class, $position, [
        'action' => $this->generateUrl('org_position_new')
      ]);

      return $this->render('admin/org/position/create_drawer.html.twig', array_merge([
        'position' => $position,
        'form' => $form->createView(),
        'drawerId' => 'position-drawer-new'
      ], $designerVariables));
    }

    if (!$positionId) {
      throw $this->createNotFoundException('岗位ID不能为空');
    }

    $position = $em->getRepository(Position::class)->find($positionId);

    if (!$position) {
      throw $this->createNotFoundException('岗位不存在');
    }

    // 获取该岗位下的员工
    $employees = $em->getRepository('App\Entity\Organization\Employee')->findBy(['position' => $position]);

    if ($action === 'edit') {
      if ($viewWithFields) {
        try {
          $generalConfig = [];
          $configFile = $this->getParameter('kernel.project_dir') . '/var/data/view_editor_config.json';
          if (file_exists($configFile)) {
            $json = file_get_contents($configFile);
            $generalConfig = json_decode($json, true) ?? [];
          }
          $result = $formFieldRenderer->render($view, $position, false, [
            'action' => $this->generateUrl('org_position_edit', ['id' => $positionId]),
          ], $generalConfig);

          return $this->render('admin/org/position/edit_drawer.html.twig', array_merge([
            'position' => $position,
            'employees' => $employees,
            'form' => $result['formView'],
            'dynamicFormHtml' => $result['html'],
            'drawerId' => 'position-drawer-' . $positionId,
          ], $designerVariables));
        } catch (\Exception $e) {
          // fallback to standard form below
        }
      }

      $form = $this->createForm(PositionType::class, $position, [
        'action' => $this->generateUrl('org_position_edit', ['id' => $positionId])
      ]);

      return $this->render('admin/org/position/edit_drawer.html.twig', array_merge([
        'position' => $position,
        'form' => $form->createView(),
        'employees' => $employees,
        'drawerId' => 'position-drawer-' . $positionId
      ], $designerVariables));
    }

    return $this->render('admin/org/position/view_drawer.html.twig', array_merge([
      'position' => $position,
      'employees' => $employees,
      'drawerId' => 'position-drawer-' . $positionId
    ], $designerVariables));
  }

  #[Route('/admin/org/user/searchByKey', name: 'org_user_search_by_key', methods: ['POST'])]
  public function searchUserByKey(Request $request, EntityManagerInterface $em): ApiResponse
  {
    $payload = $request->toArray();
    $key = $payload['key'] ?? '';
    $companyId = $payload['companyId'] ?? null;

    if (empty($key)) {
      return ApiResponse::success(json_encode([]), '200', 'success');
    }

    $repo = $em->getRepository('App\Entity\Organization\Employee');
    $depRepo = $em->getRepository(Department::class);

    $qb = $repo->createQueryBuilder('e')
      ->leftJoin('e.department', 'd')
      ->where('e.employmentStatus = :status')
      ->andWhere('(e.isSystem = false OR e.isSystem IS NULL)')
      ->setParameter('status', 'active');

    if ($companyId) {
      $qb->andWhere('e.company = :companyId')
         ->setParameter('companyId', $companyId);
    }

    $qb->andWhere(
      $qb->expr()->orX(
        'e.name LIKE :key',
        'e.employeeNo LIKE :key',
        'e.englishName LIKE :key'
      )
    )->setParameter('key', '%' . $key . '%')
     ->orderBy('e.name', 'ASC')
     ->setMaxResults(20);

    $employees = $qb->getQuery()->getResult();

    $result = [];
    foreach ($employees as $emp) {
      $deptPath = '';
      if ($emp->getDepartment()) {
        $path = $depRepo->getPath($emp->getDepartment());
        $pathNames = [];
        foreach ($path as $pathItem) {
          $itemType = $pathItem->getType();
          if ($itemType === 'corperations') continue;
          $pathNames[] = $itemType === 'company' ? ($pathItem->getAlias() ?: $pathItem->getName()) : $pathItem->getName();
        }
        $deptPath = implode('/', $pathNames);
      }

      $displayName = $emp->getName();
      $extra = [];
      if ($deptPath) $extra[] = $deptPath;
      if ($emp->getEmployeeNo()) $extra[] = $emp->getEmployeeNo();
      if (!empty($extra)) {
        $displayName .= '（' . implode(' · ', $extra) . '）';
      }

      $result[] = [
        'id' => (string) $emp->getId(),
        'displayName' => $displayName,
      ];
    }

    return ApiResponse::success(json_encode($result), '200', 'success');
  }

  /**
   * 用户选择器 - 渲染组织架构树（部门+人员）
   */
  #[Route('/admin/org/user/singleSelect', name: 'org_user_single_select', methods: ['GET'])]
  public function singleSelectUser(Request $request, EntityManagerInterface $em): Response
  {
    $companyId = $request->query->get('companyId');
    $depRepo = $em->getRepository(Department::class);
    $empRepo = $em->getRepository(Employee::class);
    $userInputId = Uuid::v1();

    $rootDepartment = null;
    if ($companyId) {
      $rootDepartment = $depRepo->findOneBy([
        'company' => $companyId,
        'type' => 'company'
      ]);
      if (!$rootDepartment) {
        $rootDepartment = $depRepo->findOneBy([
          'id' => $companyId,
          'type' => 'company'
        ]);
      }
    }

    // 预加载所有活跃员工, 按部门分组
    $qb = $empRepo->createQueryBuilder('e')
      ->where('e.employmentStatus = :status')
      ->andWhere('(e.isSystem = false OR e.isSystem IS NULL)')
      ->setParameter('status', 'active')
      ->orderBy('e.name', 'ASC');
    if ($companyId) {
      $qb->andWhere('e.company = :companyId')
         ->setParameter('companyId', $companyId);
    }
    $employees = $qb->getQuery()->getResult();

    $employeesByDept = [];
    foreach ($employees as $emp) {
      $deptId = $emp->getDepartment() ? (string) $emp->getDepartment()->getId() : '_none';
      $employeesByDept[$deptId][] = $emp;
    }

    $userSingleTree = '';
    if (!$companyId || $rootDepartment) {
      $userSingleTree = $depRepo->childrenHierarchy($rootDepartment, false, [
        'decorate' => true,
        'rootOpen' => static function (array $tree): ?string {
          static $openCount = 0;
          $openCount++;
          if ($openCount === 1) {
            return '<ol class="ol-left-tree">';
          }
          if ($tree[0]['type'] === 'department') {
            return '<span class="tree-indent" style="display: none;"></span><ol class="sub-tree-content" style="display: none;">';
          }
          return '<span class="tree-indent"></span><ol class="sub-tree-content">';
        },
        'rootClose' => static function (array $child): ?string {
          return '</ol>';
        },
        'childOpen' => '<li>',
        'childClose' => '</li>',
        'nodeDecorator' => static function (array $node) use ($userInputId, $employeesByDept, $depRepo) {
          if ($node['type'] === 'corperations') {
            return '
          <div class="item-content scroll-item">
            <div class="arrow-icon"><i class="fa-solid fa-caret-down"></i></div>
            <div class="org-icon"><i class="fa-solid fa-building"></i></div>
            <div class="org-name"><div class="org-text-content">' . htmlspecialchars($node['name']) . '</div></div>
          </div>';
          }

          if ($node['type'] === 'company') {
            $arrayIcon = !empty($node['__children']) ? '<i class="fa-solid fa-caret-right"></i>' : '';
            return '
          <div class="item-content scroll-item">
            <div class="arrow-icon">' . $arrayIcon . '</div>
            <div class="org-icon"><i class="fa-solid fa-building-user"></i></div>
            <div class="org-name"><div class="org-text-content company" type="company">' . htmlspecialchars($node['name']) . '</div></div>
          </div>';
          }

          if ($node['type'] === 'department') {
            $hasChildren = !empty($node['__children']);
            $deptEmployees = $employeesByDept[$node['id']] ?? [];
            $hasContent = $hasChildren || !empty($deptEmployees);
            $arrayIcon = $hasContent ? '<i class="fa-solid fa-caret-right"></i>' : '';

            $html = '
          <div class="item-content scroll-item">
            <div class="arrow-icon">' . $arrayIcon . '</div>
            <div class="org-icon"><i class="fa-solid fa-user-group"></i></div>
            <div class="org-name">
              <div class="org-text-content department" type="department" id="' . $node['id'] . '">' . htmlspecialchars($node['name']) . '</div>
            </div>
          </div>';

            // 在部门节点下渲染该部门的员工
            if (!empty($deptEmployees)) {
              // 构建部门全路径
              $deptPath = $node['path'] ?? $node['name'];

              $html .= '<span class="tree-indent" style="display: none;"></span><ol class="sub-tree-content" style="display: none;">';
              foreach ($deptEmployees as $emp) {
                $empName = htmlspecialchars($emp->getName());
                $empNo = htmlspecialchars($emp->getEmployeeNo() ?? '');
                $empId = (string) $emp->getId();
                $empDisplay = $empName;
                if ($empNo) $empDisplay .= ' (' . $empNo . ')';

                $html .= '<li>
                <div class="item-content scroll-item">
                  <div class="arrow-icon"></div>
                  <span class="user-select-line">
                    <label class="ef-radio" style="padding-right: 5px;" radioId="' . $userInputId . '">
                      <input type="radio" class="ef-radio-target" value="A">
                      <span class="ef-icon-hover ef-radio-icon-hover"><span class="ef-radio-icon"></span></span>
                    </label>
                    <div class="org-icon"><i class="fa-solid fa-user"></i></div>
                    <div class="org-name">
                      <div class="org-text-content user" type="user" id="' . $empId . '" 
                        data-name="' . $empName . '" 
                        data-employee-no="' . $empNo . '"
                        data-department="' . htmlspecialchars($deptPath) . '"
                        data-position="' . htmlspecialchars($emp->getPosition()?->getName() ?? '') . '"
                        data-company="' . htmlspecialchars($emp->getCompany()?->getName() ?? '') . '"
                        data-email="' . htmlspecialchars($emp->getEmail() ?? '') . '"
                        data-mobile="' . htmlspecialchars($emp->getMobile() ?? '') . '"
                        >' . $empDisplay . '</div>
                    </div>
                  </span>
                </div>
              </li>';
              }
              $html .= '</ol>';
            }

            return $html;
          }

          return '';
        }
      ]);
    }

    return $this->render('admin/org/user/singleSelect.html.twig', [
      'userSingleTree' => $userSingleTree
    ]);
  }

}
