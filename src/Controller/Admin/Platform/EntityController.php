<?php

namespace App\Controller\Admin\Platform;

use App\Lib\Str;
use App\Entity\Platform\Entity;
use App\Service\Entity\EntityService;
use App\Controller\BaseController;
use App\Controller\Api\ApiResponse;
use Symfony\Component\Finder\Finder;
use App\Entity\Platform\EntityProperty;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\Entity\EntityFormService;
use App\Entity\Platform\EntityPropertyGroup;
use App\Form\Platform\EntityModelType;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Form\Platform\EntityPropertyGroupFolderType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;


/**
 * 实体控制器
 */
class EntityController extends BaseController
{
  /**
   * 实体管理首页
   */
  #[Route('/admin/platform/entity/index', name: 'platform_entity')]
  public function index(EntityManagerInterface $em): Response
  {
    $repo = $em->getRepository(EntityPropertyGroup::class);
    $entity = $repo->childrenHierarchy(null, false, [
      'decorate' => true,
      'rootOpen' => static function (array $tree): ?string {
        if ([] !== $tree && 0 == $tree[0]['lvl']) {
          return '<ol class="ol-left-tree">';
        }

        if ($tree[0]['type'] === 'group' || $tree[0]['type'] === 'property') {
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
        if ($node['type'] === 'root') {
          return '
          <div class="item-content scroll-item">
            <div class="arrow-icon">
              <i class="fa-solid fa-caret-down"></i>
            </div>
						<div class="org-icon">
              <i class="fa-solid fa-database"></i>
						</div>
						<div class="node-name">
							<div class="tree-text-content entity-root" type="root">模型</div>
						</div>
					</div>
          ';
        }

        if ($node['type'] === 'namespace') {
          $arrayIcon = !empty($node['__children']) ? '<i class="fa-solid fa-caret-down"></i>' : '';

          return '
          <div class="item-content scroll-item">
            <div class="arrow-icon">' . $arrayIcon . '</div>
						<div class="org-icon">
              <i class="fa-regular fa-folder"></i>
						</div>
						<div class="node-name">
							<div class="tree-text-content branch namespace" type="namespace" id="' . $node['id'] . '">' .
            $node['name']
            . '</div>
						</div>
					</div>
          ';
        }

        if ($node['type'] === 'entity') {
          $arrayIcon = !empty($node['__children']) ? '<i class="fa-solid fa-caret-right"></i>' : '';

          $id = $node['id'];
          $token = $node['token'];
          $name = $node['name'];
          $entityToken = $node['entityToken'];
          return <<<html
          <div class="item-content scroll-item">
            <div class="arrow-icon">$arrayIcon</div>
						<div class="org-icon">
              <i class="fa-solid fa-table"></i>
						</div>
						<div class="node-name">
							<div class="tree-text-content branch" type="entity" id="$id" token="$token" entityToken="$entityToken">$name</div>
						</div>
					</div>
html;
        }

        if ($node['type'] === 'group') {
          $arrayIcon = !empty($node['__children']) ? '<i class="fa-solid fa-caret-right"></i>' : '';

          return '
          <div class="item-content scroll-item">
            <div class="arrow-icon">' . $arrayIcon . '</div>
						<div class="org-icon">
              <i class="fa-solid fa-folder-tree"></i>
						</div>
						<div class="node-name">
							<div class="tree-text-content branch" type="group">' . $node['label'] . '</div>
						</div>
					</div>
          ';
        }

        if ($node['type'] === 'property') {
          $arrayIcon = !empty($node['__children']) ? '<i class="fa-solid fa-caret-right"></i>' : '';

          return '
          <div class="item-content scroll-item">
            <div class="arrow-icon">' . $arrayIcon . '</div>
						<div class="org-icon">
              <i class="fa-solid fa-o"></i>
						</div>
						<div class="node-name">
							<div class="tree-text-content branch" type="property">' . $node['label'] . '</div>
						</div>
					</div>
          ';
        }
      }
    ]);
    return $this->render('admin/platform/entity/index.html.twig', [
      'entity' => $entity
    ]);
  }

  /**
   * 实体数据表格
   */
  #[Route('/admin/platform/entity/itemtable', name: 'platform_entity_itemtable')]
  public function item(Request $request, EntityManagerInterface $em, EntityService $es): Response
  {
    $repo = $em->getRepository(Entity::class);
    $epgToken = $request->query->get('token');
    $entityToken = $es->convertEpgTokentoEntityToken($epgToken);
    $entity = $repo->findOneBy(['token' => $entityToken]);
    $repoProperty = $em->getRepository(EntityProperty::class);
    $entityProperties = $repoProperty->findBy(
      ['entity' => $entity],
      ['createdAt' => 'ASC']
    );

    // 初始化表头（列名称）
    $tableHeaders = ['name', 'comment', 'type', 'length', 'entity', 'group', 'action'];

    $arr = array();
    foreach ($entityProperties as $entity) {
      $et = new \stdClass();
      $et->name = $entity->getPropertyName();
      $et->comment = $entity->getComment();
      $et->type = $entity->getType();
      $et->length = $entity->getLength();
      $et->entity = $entity->getEntity();
      $et->group = $entity->getGroup()->getLabel();
      $et->token = $entity->getToken();
      $arr[] = $et;
    }

    $button = <<<html
    <div class="toolbar-box">
      <div class="toolbar-wrap">
        <div class="toolbar-content">
          <button class="create btn outline primary medium mini round icon" token="$epgToken" entityEntity="$entityToken"><i class="fa-regular fa-square-plus"></i>添加自定义字段</button>
          <button class="group btn outline primary medium mini round icon" token="$epgToken" entityEntity="$entityToken"><i class="fa-regular fa-object-group"></i>字段分组管理</button>
        </div>
      </div>
    </div>
html
    ;

    return $this->render('ui/table.html.twig', [
      'tableHeaders' => $tableHeaders,
      'entities' => $arr,
      'toolbar' => $button,
    ]);
  }

  #[Route(
    '/admin/platform/entity/addFieldDrawer',
    name: 'platform_entity_addFieldDrawer',
    methods: ['POST']
  )]
  public function addFieldDrawer(Request $request, EntityFormService $formService): Response
  {
    $payload = $request->toArray();
    $entityToken = $payload['token'];

    $form = $formService->addField($entityToken, null, true);

    return $this->render('ui/drawer/drawer.html.twig', [
      'id' => $entityToken,
      'drawerTitle' => '添加字段',
      'width' => '840',
      'drawerContent' => $form['form'],
      'drawerTitleAddon' => $form['additional'],
      //'drawerTitleAddonTwig' => $this->_getDrawTitleAddon(),
    ]);
  }

  #[Route(
    '/admin/platform/entity/editFieldDrawer',
    name: 'platform_entity_editFieldDrawer',
    methods: ['POST']
  )]
  public function editFieldDrawer(Request $request, EntityFormService $formService): Response
  {
    $payload = $request->toArray();
    $epgToken = $payload['token'];
    $propertyToken = $payload['propertyToken'];

    $form = $formService->getEditFieldForm($epgToken, $propertyToken);

    return $this->render('ui/drawer/drawer.html.twig', [
      'id' => $epgToken . '_edit_' . $propertyToken,
      'drawerTitle' => '编辑字段',
      'width' => '840',
      'drawerContent' => $form,
      'propertyToken' => $propertyToken,
    ]);
  }

  #[Route(
    '/admin/platform/entity/addField',
    name: 'platform_entity_addField',
    methods: ['POST']
  )]
  public function addField(Request $request, EntityFormService $formService)
  {
    $payload = $request->getPayload();
    $token = $payload->get('token');
    $choosedGroup = $payload->get('choosedGroup');
    $htmlContent = $formService->addField($token, $choosedGroup, false);
    $response = new Response($htmlContent['form']);
    $response->headers->set('Content-Type', 'text/html');
    return $response;
  }

  /**
   * 新建文件夹的表单
   *
   * @param Request $request
   * @return Response
   */
  #[Route(
    '/admin/platform/entity/addFolder',
    name: 'platform_entity_addFolder',
    methods: ['GET', 'POST']
  )]
  public function addFolder(Request $request, EntityManagerInterface $em, EntityService $es): Response
  {
    // 从 GET 请求中获取上级目录 ID
    $parentId = $request->query->get('parent');
    $type = $request->query->get('type');

    // 创建新的 EntityPropertyGroup 实例
    $namespace = new EntityPropertyGroup();

    // 如果获取到上级目录 ID，则查找对应的上级命名空间
    if ($parentId) {
      $parentNamespace = $em->getRepository(EntityPropertyGroup::class)->find($parentId);

      // 如果找到上级命名空间并且它是 namespace 类型，则将其设为新命名空间的父级
      if ($parentNamespace && $parentNamespace->getType() === 'namespace') {
        $namespace->setParent($parentNamespace);
      }
    } else {
      $rootNamespace = $em->getRepository(EntityPropertyGroup::class)->findOneBy(['name' => 'root']);
      $namespace->setParent($rootNamespace);
    }

    $form = $this->createForm(EntityPropertyGroupFolderType::class, $namespace, [
      'action' => $this->generateUrl('platform_entity_addFolder')
    ]);

    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
      $post = $form->getData();

      try {
        $es->addFolderByEntity($post, $type);
      } catch (\Exception $e) {
        $this->addFlash('error', sprintf('添加文件夹失败：%s', $e->getMessage()));
        return $this->redirectToRoute('platform_entity');
      };

      $this->addFlash('success', 'flash.folder_added');
      return $this->redirectToRoute('platform_entity');
    }

    return $this->render('admin/platform/entity/folderNew.html.twig', [
      'form' => $form->createView(),
    ]);
  }


  /**
   * 新建模型Entity
   *
   * @param Request $request
   * @param EntityManagerInterface $em
   * @param EntityService $es
   * @return Response
   */
  #[Route(
    '/admin/platform/entity/addEntity',
    name: 'platform_entity_addEntity',
    methods: ['GET', 'POST']
  )]
  public function addEntity(Request $request, EntityManagerInterface $em, EntityService $es): Response
  {
    $parentId = $request->query->get('parent');
    $type = $request->query->get('type');

    $groupRepo = $em->getRepository(EntityPropertyGroup::class);
    $parent = $groupRepo->findOneBy(['id' => $parentId]);
    $entity = new Entity();
    $entity->setToken(Str::generateFieldToken());
    $fqn = $es->getFqnByEntityPropertyGroup($parent);
    $entity->setFqn($fqn);
    
    $form = $this->createForm(EntityModelType::class, $entity, [
      'action' => $this->generateUrl('platform_entity_addEntity'),
      'parent_default' => $parent,
      'type' => $type,
    ]);

    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
      // 这两个字段是额外添加的，不属于Entity模型，因此无法自动获取
      $type = $form->get('type')->getData();
      $parent = $form->get('parent')->getData();;
      $post = $form->getData();
      $post->type = $type;
      $post->parentAtEPG = $parent;

      try {
        $es->addEntity($post);
        $this->addFlash('success', 'flash.entity_added');
        return $this->redirectToRoute('platform_entity');
      } catch (\Exception $e) {
        $this->addFlash('error', sprintf('添加模型Entity失败：%s', $e->getMessage()));
        return $this->redirectToRoute('platform_entity');        
      };
    }

    return $this->render('admin/platform/entity/folderNew.html.twig', [
      'form' => $form->createView(),
    ]);
  }
}
