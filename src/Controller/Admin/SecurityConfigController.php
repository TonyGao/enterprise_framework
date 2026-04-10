<?php

namespace App\Controller\Admin;

use App\Entity\Security\PasswordResetMethod;
use App\Repository\Security\PasswordResetMethodRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use App\Entity\Security\PasswordPolicy;
use App\Entity\Security\PasswordStrengthRule;
use App\Repository\Security\PasswordPolicyRepository;
use App\Repository\Security\PasswordStrengthRuleRepository;

#[Route('/admin/security')]
#[IsGranted('ROLE_ADMIN')]
class SecurityConfigController extends AbstractController
{
    #[Route('/', name: 'admin_security_index')]
    public function index(PasswordResetMethodRepository $repository, \App\Repository\System\EmailTemplateRepository $templateRepository): Response
    {
        // Ensure default methods exist
        if ($repository->count([]) === 0) {
            return $this->redirectToRoute('admin_security_init');
        }

        $methods = $repository->findBy([], ['priority' => 'ASC']);
        $emailTemplates = $templateRepository->findAll();

        return $this->render('admin/security/index.html.twig', [
            'methods' => $methods,
            'emailTemplates' => $emailTemplates,
            'currentTab' => '2fa'
        ]);
    }

    #[Route('/password-policy', name: 'admin_security_password_policy')]
    public function passwordPolicy(
        PasswordPolicyRepository $policyRepo,
        PasswordStrengthRuleRepository $ruleRepo,
        EntityManagerInterface $em
    ): Response {
        $policy = $policyRepo->findOneBy([]);
        if (!$policy) {
            $policy = new PasswordPolicy();
            $em->persist($policy);
            $em->flush();
        }

        $rules = $ruleRepo->findBy([], ['sortOrder' => 'ASC']);

        return $this->render('admin/security/password_policy.html.twig', [
            'policy' => $policy,
            'rules' => $rules,
            'currentTab' => 'password_policy'
        ]);
    }

    #[Route('/password-policy/update', name: 'admin_security_password_policy_update', methods: ['POST'])]
    public function updatePasswordPolicy(Request $request, PasswordPolicyRepository $policyRepo, EntityManagerInterface $em): Response
    {
        $policy = $policyRepo->findOneBy([]);
        if (!$policy) {
            $policy = new PasswordPolicy();
            $em->persist($policy);
        }

        $policy->setMinLength((int) $request->request->get('min_length', 8));
        $policy->setMaxLength((int) $request->request->get('max_length', 32));
        $policy->setRequireUppercase($request->request->has('require_uppercase'));
        $policy->setRequireLowercase($request->request->has('require_lowercase'));
        $policy->setRequireNumber($request->request->has('require_number'));
        $policy->setRequireSpecial($request->request->has('require_special'));
        $policy->setForbidUsername($request->request->has('forbid_username'));
        $policy->setForbidCommonPassword($request->request->has('forbid_common_password'));
        $policy->setExpireDays((int) $request->request->get('expire_days', 90));
        $policy->setHistoryLimit((int) $request->request->get('history_limit', 3));
        $policy->setMaxRetry((int) $request->request->get('max_retry', 5));
        $policy->setLockMinutes((int) $request->request->get('lock_minutes', 30));
        $policy->setDefaultPassword((string) $request->request->get('default_password', 'Welcome@2024'));
        $policy->setForceResetPasswordOnFirstLogin($request->request->has('force_reset_password_on_first_login'));

        $em->flush();

        $this->addFlash('success', '密码策略配置已更新');

        return $this->redirectToRoute('admin_security_password_policy');
    }

    #[Route('/init', name: 'admin_security_init')]
    public function init(EntityManagerInterface $em, PasswordResetMethodRepository $repository): Response
    {
        $defaults = [
            ['key' => 'passkey', 'name' => 'Passkey', 'priority' => 10],
            ['key' => 'email', 'name' => 'Email', 'priority' => 20],
            ['key' => 'sms', 'name' => 'SMS', 'priority' => 30],
            ['key' => 'qa', 'name' => 'Security Questions', 'priority' => 40],
        ];

        foreach ($defaults as $default) {
            $method = $repository->findOneBy(['methodKey' => $default['key']]);
            if (!$method) {
                $method = new PasswordResetMethod();
                $method->setMethodKey($default['key']);
                $method->setName($default['name']);
                $method->setPriority($default['priority']);
                $method->setIsEnabled(true);
                $em->persist($method);
            }
        }

        $em->flush();
        $this->addFlash('success', 'Security methods initialized.');

        return $this->redirectToRoute('admin_security_index');
    }

    #[Route('/update/{id}', name: 'admin_security_update', methods: ['POST'])]
    public function update(Request $request, PasswordResetMethod $method, EntityManagerInterface $em): Response
    {
        $priority = $request->request->get('priority');
        $isEnabled = $request->request->has('is_enabled');
        $config = $request->request->all('config');

        if ($priority !== null) {
            $method->setPriority((int) $priority);
        }
        
        $method->setIsEnabled($isEnabled);
        
        if (!empty($config)) {
            $method->setConfig($config);
        }

        $em->flush();

        $this->addFlash('success', 'Method updated.');

        return $this->redirectToRoute('admin_security_index');
    }

    #[Route('/reorder', name: 'admin_security_reorder', methods: ['POST'])]
    public function reorder(Request $request, PasswordResetMethodRepository $repository, EntityManagerInterface $em): Response
    {
        $data = json_decode($request->getContent(), true);
        $ids = $data['ids'] ?? [];

        if (empty($ids)) {
            return $this->json(['status' => 'error', 'message' => 'No IDs provided'], 400);
        }

        foreach ($ids as $index => $id) {
            $method = $repository->find($id);
            if ($method) {
                // Priority: 10, 20, 30...
                $method->setPriority(($index + 1) * 10);
            }
        }

        $em->flush();

        return $this->json(['status' => 'success']);
    }
}
