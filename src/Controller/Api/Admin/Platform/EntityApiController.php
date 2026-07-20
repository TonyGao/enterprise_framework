<?php

namespace App\Controller\Api\Admin\Platform;

use App\Service\FileResolver;
use App\Entity\Platform\Entity;
use Nette\PhpGenerator\PhpFile;
use App\Controller\Api\ApiResponse;
use Symfony\Component\Finder\Finder;
use App\Service\Entity\EntityService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class EntityApiController extends AbstractController
{
  /**
   * 此方法用来批量增加实体字段
   * 大概过程描述如下：
   * 1，读取请求体，并分析出来需要增加的字段。
   * 2，判断字段名称是否已经存在，如果已存在就返回错误。
   * 3，实例化实体，并循环添加每个字段和相应的setter, getter。
   * 4，保存相应的字段信息到EntityProperty, EntityPropertyGroup（事务级）
   * 5，备份原Entity文件，备份路径和命名格式为 var/backup/entity/Name.php.微秒.bak
   * 6，保存新的Entity文件
   * 7，通过 Doctrine Migrations 组件进行迁移生成和应用，以最终让数据库字段同步。
   */
  #[Route(
    '/api/admin/platform/entity/batchfields',
    name: 'api_platform_entity_batchfields',
    methods: ['POST']
  )]
  public function batchFields(Request $request, EntityService $eS): ApiResponse
  {
    try {
      $payload = $request->toArray();
      $epgToken = $payload['entity']['epgToken'];
      $fields = $payload['entity']['fields'];

      $eToken = $eS->convertEpgTokentoEntityToken($epgToken);

      // 备份并加载实体
      $et = $eS->loadByToken($eToken)
        ->loadEntity();

      foreach($fields as $field) {
        // 如果是 string 类型的字段，这里是临时的判断，为了逐渐增加不同类型的Service
        if ($field['type']['value'] == 'string' || $field['type']['value'] == 'text' || $field['type']['value'] == 'user') {
          try {
            $et->addProperty($field);
          } catch (\Exception $e) {
            return ApiResponse::error('', '500', $e->getMessage());
          }
        }
      }

      $et->save();
    } catch (\Exception $e) {
      return ApiResponse::error('', '500', $e->getMessage());
    }
    
    return ApiResponse::success('', 'success', 'Added Property');
  }

  #[Route(
    '/api/admin/platform/entity/submitFields',
    name: 'api_platform_entity_submitFields',
    methods: ['POST']
  )]
  public function submitFields(Request $request)
  {
    $payload = $request->getPayload();
    return ApiResponse::success();
  }

  #[Route(
    '/api/admin/platform/entity/updateField',
    name: 'api_platform_entity_updateField',
    methods: ['POST']
  )]
  public function updateField(Request $request, EntityService $eS): ApiResponse
  {
    try {
      $payload = $request->toArray();
      $propertyToken = $payload['propertyToken'];
      $fields = $payload['fields'];

      $eS->updateProperty($propertyToken, $fields);
    } catch (\Exception $e) {
      return ApiResponse::error('', '500', $e->getMessage());
    }

    return ApiResponse::success('', 'success', 'Field updated');
  }
}
