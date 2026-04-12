<?php

namespace App\Controller\Api\Admin\Organization;

use App\Entity\Organization\Company;
use App\Entity\Organization\Department;
use App\Entity\Organization\Position;
use App\Entity\Organization\PositionLevel;
use App\Entity\Platform\Entity;
use App\Service\Platform\DataGridService;
use App\Service\Platform\CacheConfig;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use App\Controller\Api\ApiResponse;
use App\Entity\Organization\Corporation;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

class OrgApiController extends AbstractController
{
  #[Route('/api/admin/org/company/list', name: 'api_org_company_list')]
  public function listCompany(Request $request, EntityManagerInterface $em): JsonResponse
  {
    $repo = $em->getRepository(Company::class);
    $company = null;
    $company = $repo->findAll();
    $data = ['status' => 'ok'];

    return new JsonResponse($data);
  }

  #[Route(
    '/api/admin/org/company/batchcreate',
    name: 'api_org_company_batchcreate',
    methods: ['POST']
  )]
  public function batchCreateCompany(Request $request, EntityManagerInterface $em): JsonResponse
  {
    //Todo 暂时只支持单集团模式
    $corporationRepo = $em->getRepository(Corporation::class);
    $corporation = $corporationRepo->findOneBy([]);

    $payload = $request->toArray();
    $repo = $em->getRepository(Company::class);
    $root = $repo->findOneBy(['lvl' => 0]);
    foreach ($payload['company'] as $company) {
      $com = new Company();
      $com->setName($company)
        ->setAlias($company)
        ->setOwnerCompany($com)
        ->setParent($root);
      $em->persist($com);
      $em->flush();

      $depRepo = $em->getRepository(Department::class);
      $depRoot = $depRepo->findOneBy(['lvl' => 0]);
      $department = new Department();
      $department->setName($company)
        ->setAlias($company)
        ->setCompany($com)
        ->setType('company')
        ->setParent($depRoot)
        ->setOwnerCompany($com);
      $em->persist($department);
      $em->flush();
    }
    $data = ['status' => 'ok'];

    return new JsonResponse($data);
  }

  #[Route(
    '/api/admin/org/department/searchByKey',
    name: 'api_org_department_searchByKey',
    methods: ['POST']
  )]
  public function searchByKey(
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
      // 如果父级是'department'类型，那就不用再继续拼接非部门(集团、公司)的类型
      $type = $item?->getParent()?->getType();
      foreach ($path as $pathItem) {
        // 如果是集团、公司类型就用简称拼写，如果是部门就用名称拼写
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

  #[Route(
    '/api/admin/org/department/getByCompany',
    name: 'api_org_department_getByCompany',
    methods: ['GET']
  )]
  public function getDepartmentsByCompany(
    Request $request,
    EntityManagerInterface $em,
    SerializerInterface $serializer
  ): ApiResponse {
    $companyId = $request->query->get('companyId');
    $repo = $em->getRepository(Department::class);
    
    $qb = $repo->createQueryBuilder('d')
      ->where('d.state = true')
      ->andWhere('d.type = :type')
      ->setParameter('type', 'department')
      ->orderBy('d.name', 'ASC');
    
    if ($companyId) {
      $qb->andWhere('d.company = :companyId')
         ->setParameter('companyId', $companyId);
    }
    
    $data = $qb->getQuery()->getResult();

    // Build tree structure
    $tree = [];
    foreach ($data as $item) {
      $path = $repo->getPath($item);
      $pathNames = [];
      foreach ($path as $pathItem) {
        $itemType = $pathItem->getType();
        if ($itemType == 'corperations' || $itemType == 'company') {
          $pathNames[] = $pathItem->getAlias() ?: $pathItem->getName();
        } else {
          $pathNames[] = $pathItem->getName();
        }
      }
      
      $tree[] = [
        'id' => $item->getId(),
        'name' => $item->getName(),
        'alias' => $item->getAlias(),
        'companyId' => $item->getCompany()?->getId(),
        'companyName' => $item->getCompany()?->getName(),
        'path' => implode('/', $pathNames),
        'parentId' => $item->getParent()?->getId(),
      ];
    }

    $content = $serializer->serialize($tree, 'json');
    return ApiResponse::success($content, '200', 'success');
  }

  #[Route('/api/admin/org/position/list', name: 'api_org_position_list', methods: ['GET'])]
  public function positionList(Request $request, DataGridService $dataGridService): JsonResponse
  {
    $page = $request->query->getInt('page', 1);
    $pageSize = $request->query->getInt('pageSize', 20);
    $filters = $request->query->all('filters');

    // 使用 DataGridService 获取表格数据和配置
    // 可以通过 $configOverrides 参数覆盖默认配置
    $result = $dataGridService->getTableData(
      'App\\Entity\\Organization\\Position',
      [
        'page' => $page,
        'pageSize' => $pageSize,
        'filters' => $filters
      ],
      empty($filters) ? 'cached 1 hour' : null // Only cache if no filters
    );

    return $this->json($result);
  }

  #[Route('/api/admin/org/position-level/list', name: 'api_org_position_level_list', methods: ['GET'])]
  public function positionLevelList(Request $request, DataGridService $dataGridService): JsonResponse
  {
    $page = $request->query->getInt('page', 1);
    $pageSize = $request->query->getInt('pageSize', 20);
    $filters = $request->query->all('filters');

    $result = $dataGridService->getTableData(
      'App\\Entity\\Organization\\PositionLevel',
      [
        'page' => $page,
        'pageSize' => $pageSize,
        'filters' => $filters,
        'showCheckbox' => true,
        'showRowNumber' => true
      ],
      empty($filters) ? 'cached 1 hour' : null
    );

    return $this->json($result);
  }

  /**
   * 批量删除岗位级别
   */
  #[Route('/api/admin/org/position-level/batch-delete', name: 'api_org_position_level_batch_delete', methods: ['POST'])]
  public function batchDeletePositionLevel(Request $request, EntityManagerInterface $em, DataGridService $dataGridService): ApiResponse
  {
    $ids = $request->request->all('ids');
    if (empty($ids)) {
      // Support JSON body as fallback
      $payload = $request->toArray();
      $ids = $payload['ids'] ?? [];
    }

    if (empty($ids) || !is_array($ids)) {
      return ApiResponse::error(
        json_encode([]),
        400,
        '请选择要删除的岗位级别'
      );
    }

    try {
      $deletedCount = 0;
      $errors = [];

      foreach ($ids as $id) {
        $positionLevel = $em->getRepository(PositionLevel::class)->find($id);

        if (!$positionLevel) {
          $errors[] = "岗位级别ID {$id} 不存在";
          continue;
        }

        // 检查是否有岗位关联到此级别
        $positionCount = $em->getRepository(Position::class)
          ->count(['level' => $positionLevel]);

        if ($positionCount > 0) {
          $errors[] = "级别 '{$positionLevel->getName()}' 下还有 {$positionCount} 个岗位，无法删除";
          continue;
        }

        $em->remove($positionLevel);
        $deletedCount++;
      }

      if ($deletedCount > 0) {
        $em->flush();
        // 清除缓存，确保下次加载时获取最新数据
        $dataGridService->clearEntityCache(PositionLevel::class);
      }

      $message = "成功删除 {$deletedCount} 个岗位级别";
      if (!empty($errors)) {
        $message .= "，但有 " . count($errors) . " 个级别删除失败";
      }

      if ($deletedCount === 0 && !empty($errors)) {
        return ApiResponse::error(
          json_encode(['errors' => $errors]),
          400,
          implode('; ', $errors)
        );
      }

      return ApiResponse::success(
        json_encode([
          'deletedCount' => $deletedCount,
          'errors' => $errors
        ]),
        200,
        $message
      );

    } catch (\Exception $e) {
      return ApiResponse::error(
        json_encode([]),
        500,
        '删除失败：' . $e->getMessage()
      );
    }
  }

  /**
   * 批量删除岗位
   */
  #[Route('/api/admin/org/position/batch-delete', name: 'api_org_position_batch_delete', methods: ['POST'])]
  public function batchDeletePosition(Request $request, EntityManagerInterface $em, DataGridService $dataGridService): ApiResponse
  {
    $ids = $request->request->get('ids', []);

    if (empty($ids) || !is_array($ids)) {
      return ApiResponse::error(
        json_encode([]),
        400,
        '请选择要删除的岗位'
      );
    }

    try {
      $deletedCount = 0;
      $errors = [];

      foreach ($ids as $id) {
        $position = $em->getRepository(Position::class)->find($id);

        if (!$position) {
          $errors[] = "岗位ID {$id} 不存在";
          continue;
        }

        // 检查是否有员工关联到此岗位
        $employeeCount = $em->getRepository('App\\Entity\\Organization\\Employee')
          ->count(['position' => $position]);

        if ($employeeCount > 0) {
          $errors[] = "岗位 '{$position->getName()}' 下还有 {$employeeCount} 名员工，无法删除";
          continue;
        }

        // 检查是否有下级岗位
        $childPositions = $em->getRepository(Position::class)
          ->findBy(['parent' => $position]);

        if (!empty($childPositions)) {
          $errors[] = "岗位 '{$position->getName()}' 下还有下级岗位，无法删除";
          continue;
        }

        $em->remove($position);
        $deletedCount++;
      }

      if ($deletedCount > 0) {
        $em->flush();
        // 清除缓存
        $dataGridService->clearEntityCache(Position::class);
      }

      $message = "成功删除 {$deletedCount} 个岗位";
      if (!empty($errors)) {
        $message .= "，但有 " . count($errors) . " 个岗位删除失败";
      }

      return ApiResponse::success(
        json_encode([
          'deletedCount' => $deletedCount,
          'errors' => $errors
        ]),
        200,
        $message
      );

    } catch (\Exception $e) {
      return ApiResponse::error(
        json_encode([]),
        500,
        '删除失败：' . $e->getMessage()
      );
    }
  }
}
