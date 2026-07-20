<?php

namespace App\Controller\Admin;

use App\Controller\BaseController;
use App\Entity\Organization\Employee;
use App\Form\Admin\SystemAdminType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class SystemAdminController extends BaseController
{
    /**
     * 系统管理员列表
     */
    #[Route('/admin/system-admin', name: 'admin_system_admin')]
    public function index(EntityManagerInterface $em): Response
    {
        $admins = $em->getRepository(Employee::class)->findSystemUsers();

        $rows = array_map(function (Employee $a) {
            $roleLabels = array_map(fn($r) => match ($r) {
                'ROLE_SYS_ADMIN' => '超级管理员',
                'ROLE_SEC_ADMIN' => '安全管理员',
                'ROLE_AUDITOR' => '审计员',
                'ROLE_ADMIN' => '管理员',
                default => $r,
            }, $a->getRoles() ?: []);

            return [
                'id' => $a->getId(),
                'username' => $a->getUsername(),
                'name' => $a->getName(),
                'email' => $a->getEmail(),
                'mobile' => $a->getMobile() ?: '-',
                'roles' => implode(', ', $roleLabels),
                'isActive' => $a->getIsActive() ? '<span class="badge badge-success">启用</span>' : '<span class="badge badge-danger">禁用</span>',
                'lastLoginAt' => $a->getLastLoginAt() ? $a->getLastLoginAt()->format('Y-m-d H:i:s') : '-',
            ];
        }, $admins);

        return $this->render('admin/system_admin/index.html.twig', [
            'admins' => $rows,
        ]);
    }

    /**
     * 抽屉处理（查看/编辑/新建）
     */
    #[Route('/admin/system-admin/drawer', name: 'admin_system_admin_drawer', methods: ['POST'])]
    public function drawer(
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        $payload = $request->toArray();
        $adminId = $payload['adminId'] ?? null;
        $action = $payload['action'] ?? 'view';

        if ($action === 'create') {
            $admin = new Employee();
            $admin->setIsSystem(true);
            $admin->setEmploymentStatus('active');

            $form = $this->createForm(SystemAdminType::class, $admin, [
                'action' => $this->generateUrl('admin_system_admin_create'),
                'is_edit' => false,
            ]);

            return $this->render('admin/system_admin/form_drawer.html.twig', [
                'admin' => $admin,
                'form' => $form->createView(),
                'drawerId' => 'system-admin-drawer-new',
                'title' => '新建系统管理员',
            ]);
        }

        if (!$adminId) {
            throw $this->createNotFoundException('管理员ID不能为空');
        }

        $admin = $em->getRepository(Employee::class)->find($adminId);
        if (!$admin || !$admin->getIsSystem()) {
            throw $this->createNotFoundException('系统管理员不存在');
        }

        if ($action === 'edit') {
            $form = $this->createForm(SystemAdminType::class, $admin, [
                'action' => $this->generateUrl('admin_system_admin_edit', ['id' => $adminId]),
                'is_edit' => true,
            ]);

            return $this->render('admin/system_admin/form_drawer.html.twig', [
                'admin' => $admin,
                'form' => $form->createView(),
                'drawerId' => 'system-admin-drawer-' . $adminId,
                'title' => '编辑系统管理员',
            ]);
        }

        // 默认 view
        return $this->render('admin/system_admin/view_drawer.html.twig', [
            'admin' => $admin,
            'drawerId' => 'system-admin-drawer-' . $adminId,
        ]);
    }

    /**
     * 创建系统管理员（表单提交）
     */
    #[Route('/admin/system-admin/create', name: 'admin_system_admin_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        $admin = new Employee();
        $admin->setIsSystem(true);
        $admin->setEmploymentStatus('active');

        $form = $this->createForm(SystemAdminType::class, $admin, [
            'is_edit' => false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $admin = $form->getData();
            $plainPassword = $form->get('password')->getData();
            if ($plainPassword) {
                $admin->setPassword($passwordHasher->hashPassword($admin, $plainPassword));
            }
            $em->persist($admin);
            $em->flush();
            $this->addFlash('success', '系统管理员创建成功');
        } else {
            $this->addFlash('error', '创建失败，请检查表单');
        }

        return $this->redirectToRoute('admin_system_admin');
    }

    /**
     * 编辑系统管理员（表单提交）
     */
    #[Route('/admin/system-admin/edit/{id}', name: 'admin_system_admin_edit', methods: ['POST'])]
    public function edit(
        Request $request,
        EntityManagerInterface $em,
        string $id,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        $admin = $em->getRepository(Employee::class)->find($id);
        if (!$admin || !$admin->getIsSystem()) {
            throw $this->createNotFoundException('系统管理员不存在');
        }

        $form = $this->createForm(SystemAdminType::class, $admin, [
            'is_edit' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $admin = $form->getData();
            if ($form->has('password')) {
                $plainPassword = $form->get('password')->getData();
                if ($plainPassword) {
                    $admin->setPassword($passwordHasher->hashPassword($admin, $plainPassword));
                }
            }
            $em->flush();
            $this->addFlash('success', '系统管理员更新成功');
        } else {
            $this->addFlash('error', '更新失败，请检查表单');
        }

        return $this->redirectToRoute('admin_system_admin');
    }
}
