<?php

namespace App\Controller\Test;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\RequestStack;
use App\Entity\Organization\Department;
use App\Entity\Organization\Employee;
use App\Controller\Api\ApiResponse;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Uid\Uuid;

class TestController extends AbstractController
{

  public function __construct(
    private RequestStack $requestStack,
  ) {
  }

  #[Route('/test/tree', methods: ['GET'], name: 'test_tree')]
  public function tree(EntityManagerInterface $em): Response
  {
      $repo = $em->getRepository(Department::class);
      
      // Fetch the tree as a nested array (decorate = false)
      $tree = $repo->childrenHierarchy(null, false, [
          'decorate' => false,
          'rootOpen' => null,
          'rootClose' => null,
          'childOpen' => null,
          'childClose' => null,
          'nodeDecorator' => null
      ]);

      // Mock data for icon showcase
      $mockTree = [
          [
              'id' => 'root-1',
              'name' => '系统模型根节点',
              'type' => 'root',
              '__children' => [
                  [
                      'id' => 'ns-1',
                      'name' => '基础模块',
                      'type' => 'namespace',
                      '__children' => [
                          [
                              'id' => 'entity-1',
                              'name' => '用户实体',
                              'type' => 'entity',
                              '__children' => [
                                  [
                                      'id' => 'group-1',
                                      'name' => '基本信息',
                                      'type' => 'group',
                                      '__children' => [
                                          ['id' => 'prop-1', 'name' => '姓名', 'type' => 'property', '__children' => []],
                                          ['id' => 'prop-2', 'name' => '邮箱', 'type' => 'property', '__children' => []],
                                      ]
                                  ]
                              ]
                          ]
                      ]
                  ],
                  [
                      'id' => 'ns-2',
                      'name' => '业务模块',
                      'type' => 'namespace',
                      '__children' => []
                  ]
              ]
          ]
      ];

      // Mock data for menu showcase (draggable + checkbox)
      $mockMenuTree = [
          [
              'id' => 'menu-root',
              'name' => '系统菜单',
              'type' => 'root',
              '__children' => [
                  [
                      'id' => 'menu-1',
                      'name' => 'Dashboard',
                      'type' => 'menu',
                      '__children' => []
                  ],
                  [
                      'id' => 'menu-2',
                      'name' => '系统管理',
                      'type' => 'menu',
                      '__children' => [
                          ['id' => 'menu-2-1', 'name' => '用户管理', 'type' => 'menu', '__children' => []],
                          ['id' => 'menu-2-2', 'name' => '角色管理', 'type' => 'menu', '__children' => []],
                          ['id' => 'menu-2-3', 'name' => '菜单管理', 'type' => 'menu', '__children' => []],
                      ]
                  ],
                  [
                      'id' => 'menu-3',
                      'name' => '设置',
                      'type' => 'menu',
                      '__children' => []
                  ]
              ]
          ]
      ];

      return $this->render('test/tree.html.twig', [
          'tree' => $tree,
          'mockTree' => $mockTree,
          'mockMenuTree' => $mockMenuTree
      ]);
  }

  #[Route('/test/department', methods: ['GET'], name: 'test_department')]
  public function department(EntityManagerInterface $em): Response
  {
    $departmentRepo = $em->getRepository(Department::class);
    $companyNodes = $departmentRepo->findBy([
      'type' => 'company',
      'state' => true,
    ], ['lft' => 'ASC']);

    $companyOptions = array_map(static fn(Department $companyNode) => [
      'label' => $companyNode->getAlias() ?: $companyNode->getName(),
      'value' => $companyNode->getId(),
    ], $companyNodes);

    return $this->render('test/department.html.twig', [
      'companyOptions' => $companyOptions,
    ]);
  }

  #[Route('/test/api/department/search', methods: ['POST'], name: 'test_department_search')]
  public function searchDepartments(
    Request $request,
    EntityManagerInterface $em,
    SerializerInterface $serializer
  ): ApiResponse {
    $payload = $request->toArray();
    $repo = $em->getRepository(Department::class);
    $qb = $repo->createQueryBuilder('d');
    $qb
      ->andWhere($qb->expr()->orX('d.alias LIKE :key', 'd.name LIKE :key'))
      ->andWhere('d.state = true')
      ->andWhere('d.type = :type')
      ->setParameter('key', '%' . $payload['key'] . '%')
      ->setParameter('type', 'department');

    // Filter by selected company subtree if provided.
    if (!empty($payload['companyId'])) {
      $rootDepartment = $repo->findOneBy([
        'company' => $payload['companyId'],
        'type' => 'company'
      ]);

      if (!$rootDepartment) {
        $rootDepartment = $repo->findOneBy([
          'id' => $payload['companyId'],
          'type' => 'company'
        ]);
      }

      if (!$rootDepartment) {
        $data = [];
        $content = $serializer->serialize($data, 'json');
        return ApiResponse::success($content, '200', 'success');
      }

      $qb->andWhere('d.root = :treeRoot')
         ->andWhere('d.lft > :rootLft')
         ->andWhere('d.rgt < :rootRgt')
         ->setParameter('treeRoot', $rootDepartment->getRoot() ?: $rootDepartment)
         ->setParameter('rootLft', $rootDepartment->getLft())
         ->setParameter('rootRgt', $rootDepartment->getRgt());
    }

    $data = $qb->getQuery()->getResult();

    foreach ($data as $item) {
      $name = '';
      $path = $repo->getPath($item);
      // If parent is 'department' type, no need to concatenate non-department types
      $type = $item?->getParent()?->getType();
      foreach ($path as $pathItem) {
        // If group/company type, use alias; if department type, use name
        $itemType = $pathItem->getType();
        if ($itemType == 'corperations' || $itemType == 'company') {
          $name .= $pathItem->getAlias() . '/';
        }
        if ($pathItem->getType() == 'department') {
          $name .= $pathItem->getName() . '/';
        }
      }
      $name = rtrim($name, "/");
      $item->setDisplayName($name);
    }

    $content = $serializer->serialize($data, 'json', ['groups' => ['api']]);
    return ApiResponse::success($content, '200', 'success');
  }

  #[Route('/test/api/department/singleSelect', methods: ['GET'], name: 'test_department_single_select')]
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

  #[Route('/test/api/user/searchByKey', methods: ['POST'], name: 'test_user_search_by_key')]
  public function searchUserByKey(Request $request, EntityManagerInterface $em): ApiResponse
  {
    $payload = $request->toArray();
    $key = $payload['key'] ?? '';
    $companyId = $payload['companyId'] ?? null;

    if (empty($key)) {
      return ApiResponse::success(json_encode([]), '200', 'success');
    }

    $repo = $em->getRepository(Employee::class);
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

  #[Route('/test/api/user/singleSelect', methods: ['GET'], name: 'test_user_single_select')]
  public function singleSelectUser(Request $request, EntityManagerInterface $em): Response
  {
    $companyId = $request->query->get('companyId');
    $depRepo = $em->getRepository(Department::class);
    $empRepo = $em->getRepository(Employee::class);
    $userInputId = Uuid::v1();

    $rootDepartment = null;
    if ($companyId) {
      $rootDepartment = $depRepo->findOneBy(['company' => $companyId, 'type' => 'company']);
      if (!$rootDepartment) {
        $rootDepartment = $depRepo->findOneBy(['id' => $companyId, 'type' => 'company']);
      }
    }

    $qb = $empRepo->createQueryBuilder('e')
      ->where('e.employmentStatus = :status')
      ->andWhere('(e.isSystem = false OR e.isSystem IS NULL)')
      ->setParameter('status', 'active')
      ->orderBy('e.name', 'ASC');
    if ($companyId) {
      $qb->andWhere('e.company = :companyId')->setParameter('companyId', $companyId);
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
          if ($openCount === 1) return '<ol class="ol-left-tree">';
          if ($tree[0]['type'] === 'department') return '<span class="tree-indent" style="display: none;"></span><ol class="sub-tree-content" style="display: none;">';
          return '<span class="tree-indent"></span><ol class="sub-tree-content">';
        },
        'rootClose' => static fn(array $child): string => '</ol>',
        'childOpen' => '<li>',
        'childClose' => '</li>',
        'nodeDecorator' => static function (array $node) use ($userInputId, $employeesByDept) {
          $nodeId = (string) $node['id'];
          if ($node['type'] === 'corperations') {
            return '<div class="item-content scroll-item"><div class="arrow-icon"><i class="fa-solid fa-caret-down"></i></div><div class="org-icon"><i class="fa-solid fa-building"></i></div><div class="org-name"><div class="org-text-content">' . htmlspecialchars($node['name']) . '</div></div></div>';
          }
          if ($node['type'] === 'company') {
            $arrayIcon = !empty($node['__children']) ? '<i class="fa-solid fa-caret-right"></i>' : '';
            return '<div class="item-content scroll-item"><div class="arrow-icon">' . $arrayIcon . '</div><div class="org-icon"><i class="fa-solid fa-building-user"></i></div><div class="org-name"><div class="org-text-content company" type="company">' . htmlspecialchars($node['name']) . '</div></div></div>';
          }
          if ($node['type'] === 'department') {
            $deptEmployees = $employeesByDept[$nodeId] ?? [];
            $hasContent = !empty($node['__children']) || !empty($deptEmployees);
            $arrayIcon = $hasContent ? '<i class="fa-solid fa-caret-right"></i>' : '';
            $deptPath = $node['path'] ?? $node['name'];

            $html = '<div class="item-content scroll-item"><div class="arrow-icon">' . $arrayIcon . '</div><div class="org-icon"><i class="fa-solid fa-user-group"></i></div><div class="org-name"><div class="org-text-content department" type="department" id="' . $nodeId . '">' . htmlspecialchars($node['name']) . '</div></div></div>';

            if (!empty($deptEmployees)) {
              $html .= '<span class="tree-indent" style="display: none;"></span><ol class="sub-tree-content" style="display: none;">';
              foreach ($deptEmployees as $emp) {
                $empName = htmlspecialchars($emp->getName());
                $empNo = htmlspecialchars($emp->getEmployeeNo() ?? '');
                $empId = (string) $emp->getId();
                $empDisplay = $empName . ($empNo ? ' (' . $empNo . ')' : '');
                $html .= '<li><div class="item-content scroll-item"><div class="arrow-icon"></div><span class="user-select-line"><label class="ef-radio" style="padding-right: 5px;" radioId="' . $userInputId . '"><input type="radio" class="ef-radio-target" value="A"><span class="ef-icon-hover ef-radio-icon-hover"><span class="ef-radio-icon"></span></span></label><div class="org-icon"><i class="fa-solid fa-user"></i></div><div class="org-name"><div class="org-text-content user" type="user" id="' . $empId . '" data-name="' . $empName . '" data-employee-no="' . $empNo . '" data-department="' . htmlspecialchars($deptPath) . '" data-position="' . htmlspecialchars($emp->getPosition()?->getName() ?? '') . '" data-company="' . htmlspecialchars($emp->getCompany()?->getName() ?? '') . '" data-email="' . htmlspecialchars($emp->getEmail() ?? '') . '" data-mobile="' . htmlspecialchars($emp->getMobile() ?? '') . '">' . $empDisplay . '</div></div></span></div></li>';
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

  #[Route('/test/{element}', methods: ['GET'], name: 'element_page')]
  public function element(Request $request, $element): Response
  {
    $session = $this->requestStack->getSession();
    // $session->setId("AAA");
    $template = 'test/' . $element . '.html.twig';
    return $this->render($template);
  }

  #[Route('/', methods: ['GET'], name: 'index_page')]
  public function index(Request $request): Response
  {
    return $this->render('test/test.html.twig');
  }
}
