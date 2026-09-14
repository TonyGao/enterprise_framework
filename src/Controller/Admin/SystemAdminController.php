<?php

namespace App\Controller\Admin;

use App\Controller\BaseController;
use App\Entity\Organization\Employee;
use App\Form\Admin\SystemAdminType;
use App\Service\Security\PasskeyReauthService;
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
            $this->addFlash('success', 'flash.admin_created');
        } else {
            $this->addFlash('error', 'flash.create_failed_form');
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
            $this->addFlash('success', 'flash.admin_updated');
        } else {
            $this->addFlash('error', 'flash.update_failed_form');
        }

        return $this->redirectToRoute('admin_system_admin');
    }

    /**
     * 重置密码 - 第一步：生成 Passkey 断言选项（用操作者自己的 Passkey 做二次验证，
     * 避免"忘记密码却还要输入密码"的悖论）/
     * Reset password step 1: create WebAuthn assertion options for the operator's own passkey.
     */
    #[Route('/admin/system-admin/reset-password/passkey/options', name: 'admin_system_admin_reset_passkey_options', methods: ['POST'])]
    public function resetPasswordPasskeyOptions(
        Request $request,
        EntityManagerInterface $em,
        PasskeyReauthService $passkey,
    ): Response {
        $payload = $request->toArray();
        $targetId = (string) ($payload['id'] ?? '');
        $admin = $em->getRepository(Employee::class)->find($targetId);
        if (!$admin || !$admin->getIsSystem()) {
            return $this->json(['code' => 404, 'message' => 'sysAdminIndex.reset.not_found']);
        }

        $operator = $this->getUser();
        if (!$operator instanceof Employee || !$passkey->hasPasskey($operator)) {
            return $this->json(['code' => 400, 'message' => 'sysAdminIndex.reset.no_passkey']);
        }

        $options = $passkey->createOptions($operator);
        if ($options === null) {
            return $this->json(['code' => 400, 'message' => 'sysAdminIndex.reset.no_passkey']);
        }

        return $this->json(['code' => 200, 'options' => $options]);
    }

    /**
     * 重置密码 - 第二步：校验 Passkey 断言；成功后在本会话登记一次性"已验证"标记（绑定目标管理员，5 分钟有效）/
     * Reset password step 2: verify the assertion and mark this session as verified (one-time, 5 min).
     */
    #[Route('/admin/system-admin/reset-password/passkey/verify', name: 'admin_system_admin_reset_passkey_verify', methods: ['POST'])]
    public function resetPasswordPasskeyVerify(
        Request $request,
        EntityManagerInterface $em,
        PasskeyReauthService $passkey,
    ): Response {
        $targetId = (string) $request->query->get('id', '');
        $admin = $em->getRepository(Employee::class)->find($targetId);
        if (!$admin || !$admin->getIsSystem()) {
            return $this->json(['code' => 404, 'message' => 'sysAdminIndex.reset.not_found']);
        }

        $operator = $this->getUser();
        if (!$operator instanceof Employee) {
            return $this->json(['code' => 403, 'message' => 'sysAdminIndex.reset.not_found']);
        }

        try {
            $ok = $passkey->verify((string) $request->getContent(), $request->getHost());
        } catch (\Throwable) {
            $ok = false;
        }
        if (!$ok) {
            return $this->json(['code' => 400, 'message' => 'sysAdminIndex.reset.passkey_failed']);
        }

        // 签发短时效、绑定该管理员的签名令牌，交由前端在重置时回传（无状态，不依赖 session）/
        // issue a short-lived signed token bound to this admin; the client returns it on reset
        $token = $passkey->issueResetToken($targetId, 300);

        return $this->json(['code' => 200, 'message' => 'sysAdminIndex.reset.passkey_ok', 'reset_token' => $token]);
    }

    /**
     * 重置系统管理员密码（需先通过 Passkey 二次验证）/
     * Reset a system admin password (requires prior passkey re-authentication).
     */
    #[Route('/admin/system-admin/reset-password/{id}', name: 'admin_system_admin_reset_password', methods: ['POST'])]
    public function resetPassword(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        PasskeyReauthService $passkey,
        string $id,
    ): Response {
        $admin = $em->getRepository(Employee::class)->find($id);
        if (!$admin || !$admin->getIsSystem()) {
            return $this->json(['code' => 404, 'message' => 'sysAdminIndex.reset.not_found']);
        }

        // 二次验证：必须携带 Passkey 验证后签发的、绑定该管理员的、未过期的令牌 /
        // 2FA: require the signed, admin-bound, unexpired token issued after passkey verification
        $token = (string) $request->request->get('reset_token', '');
        if ($token === '' || !$passkey->validateResetToken($token, (string) $id)) {
            return $this->json(['code' => 403, 'message' => 'sysAdminIndex.reset.passkey_required']);
        }

        $newPassword = (string) $request->request->get('new_password', '');
        if (mb_strlen($newPassword) < 8) {
            return $this->json(['code' => 400, 'message' => 'sysAdminIndex.reset.weak_password']);
        }

        $admin->setPassword($passwordHasher->hashPassword($admin, $newPassword));
        $em->flush();

        return $this->json(['code' => 200, 'message' => 'sysAdminIndex.reset.success']);
    }
}
