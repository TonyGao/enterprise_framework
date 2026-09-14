<?php

namespace App\Controller\Admin;

use App\Entity\Storage\StorageConfig;
use App\Form\Storage\StorageConfigType;
use App\Repository\Storage\StorageConfigRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/storage')]
#[IsGranted('ROLE_ADMIN')]
class StorageConfigController extends AbstractController
{
    #[Route('/', name: 'admin_storage_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $columns = [
            ['field' => 'name', 'label' => '配置名称', 'align' => 'center', 'visible' => true],
            ['field' => 'adapterType', 'label' => '存储类型', 'align' => 'center', 'raw' => true, 'visible' => true],
            ['field' => 'isDefault', 'label' => '默认配置', 'align' => 'center', 'raw' => true, 'visible' => true],
            ['field' => 'cdnDomain', 'label' => 'CDN域名', 'align' => 'center', 'visible' => true],
            ['field' => 'createdAt', 'label' => '创建时间', 'align' => 'center', 'visible' => true],
        ];

        return $this->render('admin/storage/index.html.twig', [
            'tableData' => [], // Grid will load data via AJAX
            'columns' => $columns,
            'currentPage' => $request->query->getInt('page', 1),
            'pageSize' => $request->query->getInt('pageSize', 20),
        ]);
    }

    #[Route('/api/list', name: 'api_admin_storage_list', methods: ['GET'])]
    public function list(Request $request, StorageConfigRepository $repository, CsrfTokenManagerInterface $csrfTokenManager): JsonResponse
    {
        $page = $request->query->getInt('page', 1);
        $pageSize = $request->query->getInt('pageSize', 20);

        $queryBuilder = $repository->createQueryBuilder('s');

        // Count total items
        $totalItems = (clone $queryBuilder)
            ->select('count(s.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Get paginated results
        $configs = $queryBuilder
            ->orderBy('s.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $pageSize)
            ->setMaxResults($pageSize)
            ->getQuery()
            ->getResult();

        $data = [];
        foreach ($configs as $config) {
            $adapterTypeHtml = match ($config->getAdapterType()) {
                'local' => '<span class="ef-tag ef-tag-blue">本地存储</span>',
                's3' => '<span class="ef-tag ef-tag-orange">S3对象存储</span>',
                default => $config->getAdapterType(),
            };
            
            $isDefaultHtml = $config->isDefault() 
                ? '<span class="ef-tag ef-tag-green">是</span>' 
                : '<span class="ef-tag ef-tag-grey">否</span>';

            $data[] = [
                'id' => $config->getId(),
                'name' => $config->getName(),
                'adapterType' => $adapterTypeHtml,
                'isDefault' => $isDefaultHtml,
                'cdnDomain' => $config->getCdnDomain(),
                'createdAt' => $config->getCreatedAt() ? $config->getCreatedAt()->format('Y-m-d H:i:s') : '',
            ];
        }

        $gridConfig = [
            'columns' => [
                ['field' => 'name', 'label' => '配置名称', 'align' => 'center', 'visible' => true],
                ['field' => 'adapterType', 'label' => '存储类型', 'align' => 'center', 'raw' => true, 'visible' => true],
                ['field' => 'isDefault', 'label' => '默认配置', 'align' => 'center', 'raw' => true, 'visible' => true],
                ['field' => 'cdnDomain', 'label' => 'CDN域名', 'align' => 'center', 'visible' => true],
                ['field' => 'createdAt', 'label' => '创建时间', 'align' => 'center', 'visible' => true],
                ['field' => 'actions', 'label' => '操作', 'align' => 'center', 'visible' => true],
            ]
        ];

        return new JsonResponse([
            'data' => $data,
            'totalItems' => $totalItems,
            'totalPages' => ceil($totalItems / $pageSize),
            'currentPage' => $page,
            'pageSize' => $pageSize,
            'gridConfig' => $gridConfig
        ]);
    }

    #[Route('/batch-delete', name: 'admin_storage_batch_delete', methods: ['POST'])]
    public function batchDelete(Request $request, StorageConfigRepository $repository, EntityManagerInterface $entityManager, CsrfTokenManagerInterface $csrfTokenManager): JsonResponse
    {
        $token = $request->request->get('_token');
        if (!$csrfTokenManager->isTokenValid(new \Symfony\Component\Security\Csrf\CsrfToken('batch_delete', $token))) {
             return new JsonResponse(['status' => 'error', 'message' => 'Invalid CSRF token'], 403);
        }

        $ids = $request->request->all('ids');
        if (empty($ids)) {
            return new JsonResponse(['status' => 'error', 'message' => 'No items selected'], 400);
        }

        $count = 0;
        foreach ($ids as $id) {
            $config = $repository->find($id);
            if ($config) {
                $entityManager->remove($config);
                $count++;
            }
        }

        $entityManager->flush();

        return new JsonResponse(['status' => 'success', 'message' => "Deleted $count items"]);
    }

    #[Route('/view/{id}', name: 'admin_storage_view', methods: ['GET'])]
    public function view(StorageConfig $storageConfig): Response
    {
        return $this->render('admin/storage/view_drawer.html.twig', [
            'storage_config' => $storageConfig,
            'drawerId' => 'storage-config-drawer-' . $storageConfig->getId()
        ]);
    }
    #[Route('/drawer', name: 'admin_storage_drawer', methods: ['POST'])]
    public function drawer(Request $request, StorageConfigRepository $repository): Response
    {
        $payload = $request->getPayload();
        $action = $payload->get('action');
        $id = $payload->get('id');

        if ($action === 'create') {
            $storageConfig = new StorageConfig();
            $form = $this->createForm(StorageConfigType::class, $storageConfig, [
                'action' => $this->generateUrl('admin_storage_new')
            ]);
            return $this->render('admin/storage/create_drawer.html.twig', [
                'form' => $form->createView(),
                'drawerId' => 'storage-config-drawer-new'
            ]);
        } elseif ($action === 'edit' && $id) {
            $storageConfig = $repository->find($id);
            if (!$storageConfig) {
                return new Response('Config not found', 404);
            }
            $form = $this->createForm(StorageConfigType::class, $storageConfig, [
                'action' => $this->generateUrl('admin_storage_edit', ['id' => $id])
            ]);
            return $this->render('admin/storage/edit_drawer.html.twig', [
                'form' => $form->createView(),
                'drawerId' => 'storage-config-drawer-' . $id,
                'storage_config' => $storageConfig
            ]);
        } elseif ($action === 'view' && $id) {
            $storageConfig = $repository->find($id);
            if (!$storageConfig) {
                return new Response('Config not found', 404);
            }
            return $this->render('admin/storage/view_drawer.html.twig', [
                'drawerId' => 'storage-config-drawer-' . $id,
                'storage_config' => $storageConfig
            ]);
        }

        return new Response('Invalid action', 400);
    }

    #[Route('/new', name: 'admin_storage_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $storageConfig = new StorageConfig();
        $form = $this->createForm(StorageConfigType::class, $storageConfig);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($storageConfig);
            $entityManager->flush();

            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['status' => 'success', 'message' => 'msg.storage.created']);
            }

            return $this->redirectToRoute('admin_storage_index', [], Response::HTTP_SEE_OTHER);
        }

        if ($request->isXmlHttpRequest() && $form->isSubmitted()) {
             // If validation failed, return the form with errors (rendered as HTML)
             // The client-side logic (ui.formValid) usually replaces the form content with this HTML
             // However, for drawers, we might need to return the drawer content again.
             // But wait, ui.formValid is generic. Let's assume it handles HTML response for errors.
             return $this->render('admin/storage/create_drawer.html.twig', [
                'form' => $form->createView(),
                'drawerId' => 'storage-config-drawer-new'
            ], new Response(null, 422)); // Return 422 for validation errors
        }

        return $this->render('admin/storage/form.html.twig', [
            'storage_config' => $storageConfig,
            'form' => $form->createView(),
            'title' => '新建存储配置'
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_storage_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, StorageConfig $storageConfig, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(StorageConfigType::class, $storageConfig);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['status' => 'success', 'message' => 'msg.storage.updated']);
            }

            return $this->redirectToRoute('admin_storage_index', [], Response::HTTP_SEE_OTHER);
        }

        if ($request->isXmlHttpRequest() && $form->isSubmitted()) {
            return $this->render('admin/storage/edit_drawer.html.twig', [
                'form' => $form->createView(),
                'drawerId' => 'storage-config-drawer-' . $storageConfig->getId(),
                'storage_config' => $storageConfig
            ], new Response(null, 422));
        }

        return $this->render('admin/storage/form.html.twig', [
            'storage_config' => $storageConfig,
            'form' => $form->createView(),
            'title' => '编辑存储配置'
        ]);
    }

    #[Route('/{id}', name: 'admin_storage_delete', methods: ['POST'])]
    public function delete(Request $request, StorageConfig $storageConfig, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$storageConfig->getId(), $request->request->get('_token'))) {
            $entityManager->remove($storageConfig);
            $entityManager->flush();
        }

        return $this->redirectToRoute('admin_storage_index', [], Response::HTTP_SEE_OTHER);
    }
}
