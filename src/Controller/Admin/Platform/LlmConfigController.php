<?php

namespace App\Controller\Admin\Platform;

use App\Controller\BaseController;
use App\Entity\Platform\LlmProvider;
use App\Entity\Platform\LlmRole;
use App\Form\Platform\LlmProviderType;
use App\Form\Platform\LlmRoleType;
use App\Repository\Platform\LlmProviderRepository;
use App\Repository\Platform\LlmRoleRepository;
use App\Service\Platform\Llm\LlmGatewayFactory;
use App\Service\Platform\LlmEncryptor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/platform/llm-config')]
#[IsGranted('ROLE_ADMIN')]
class LlmConfigController extends BaseController
{
    #[Route('', name: 'admin_llm_config_index')]
    public function index(
        LlmProviderRepository $providerRepo,
        LlmRoleRepository $roleRepo
    ): Response {
        return $this->render('admin/platform/llm_config/index.html.twig', [
            'providers' => $providerRepo->findBy([], ['orderNum' => 'ASC']),
            'roles' => $roleRepo->findBy([], ['code' => 'ASC']),
        ]);
    }

    #[Route('/provider/create', name: 'admin_llm_provider_create')]
    public function createProvider(
        Request $request,
        EntityManagerInterface $em,
        LlmEncryptor $encryptor
    ): Response {
        $provider = new LlmProvider();
        $form = $this->createForm(LlmProviderType::class, $provider, [
            'is_new' => true,
        ]);
        $this->prefillThinkingOptions($form, $provider);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($apiKey = $form->get('apiKey')->getData()) {
                $provider->setApiKeyEncrypted($encryptor->encrypt($apiKey));
            }
            $provider->setOptions($this->buildOptions($form));
            $em->persist($provider);
            $em->flush();
            $this->addFlash('success', '服务商创建成功');
            return $this->redirectToRoute('admin_llm_config_index');
        }

        return $this->render('admin/platform/llm_config/provider_form.html.twig', [
            'form' => $form->createView(),
            'provider' => $provider,
        ]);
    }

    #[Route('/provider/{id}/edit', name: 'admin_llm_provider_edit')]
    public function editProvider(
        Request $request,
        LlmProvider $provider,
        EntityManagerInterface $em,
        LlmEncryptor $encryptor
    ): Response {
        $form = $this->createForm(LlmProviderType::class, $provider, [
            'is_new' => false,
        ]);

        // 回显掩码
        if ($provider->getApiKeyEncrypted()) {
            $decrypted = $encryptor->decrypt($provider->getApiKeyEncrypted());
            if ($decrypted !== null) {
                $form->get('apiKey')->setData('••••••••' . substr($decrypted, -4));
            }
        }

        $this->prefillThinkingOptions($form, $provider);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($apiKey = $form->get('apiKey')->getData()) {
                if (!str_starts_with($apiKey, '••••••••')) {
                    $provider->setApiKeyEncrypted($encryptor->encrypt($apiKey));
                }
            }
            $provider->setOptions($this->buildOptions($form));
            $em->flush();
            $this->addFlash('success', '服务商编辑成功');
            return $this->redirectToRoute('admin_llm_config_index');
        }

        return $this->render('admin/platform/llm_config/provider_form.html.twig', [
            'form' => $form->createView(),
            'provider' => $provider,
        ]);
    }

    #[Route('/provider/{id}/delete', name: 'admin_llm_provider_delete', methods: ['POST'])]
    public function deleteProvider(
        LlmProvider $provider,
        EntityManagerInterface $em
    ): Response {
        // 解除所有角色的绑定
        $roleRepo = $em->getRepository(LlmRole::class);
        $roles = $roleRepo->findBy(['provider' => $provider]);
        foreach ($roles as $role) {
            $role->setProvider(null);
        }
        $em->remove($provider);
        $em->flush();
        $this->addFlash('success', '服务商已删除');
        return $this->redirectToRoute('admin_llm_config_index');
    }

    #[Route('/provider/{id}/toggle', name: 'admin_llm_provider_toggle', methods: ['POST'])]
    public function toggleProvider(
        LlmProvider $provider,
        EntityManagerInterface $em
    ): JsonResponse {
        $provider->setIsEnabled(!$provider->isEnabled());
        $em->flush();
        return $this->json([
            'success' => true,
            'isEnabled' => $provider->isEnabled(),
        ]);
    }

    #[Route('/role/{code}/edit', name: 'admin_llm_role_edit')]
    public function editRole(
        Request $request,
        string $code,
        LlmRoleRepository $roleRepo,
        EntityManagerInterface $em
    ): Response {
        $role = $roleRepo->findOneBy(['code' => $code]);
        if (!$role) {
            throw $this->createNotFoundException('角色不存在');
        }
        $form = $this->createForm(LlmRoleType::class, $role);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', '角色编辑成功');
            return $this->redirectToRoute('admin_llm_config_index');
        }

        return $this->render('admin/platform/llm_config/role_form.html.twig', [
            'form' => $form->createView(),
            'role' => $role,
        ]);
    }

    #[Route('/role/batch-bind', name: 'admin_llm_role_batch_bind', methods: ['POST'])]
    public function batchBindRoles(
        Request $request,
        LlmRoleRepository $roleRepo,
        LlmProviderRepository $providerRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        $roleCodes = $request->request->all('role_codes') ?? [];
        $providerId = $request->request->get('provider_id');

        if (empty($roleCodes)) {
            return $this->json(['success' => false, 'error' => '请选择至少一个角色'], 422);
        }

        $provider = null;
        if ($providerId) {
            $provider = $providerRepo->find(Uuid::fromString($providerId));
            if (!$provider) {
                return $this->json(['success' => false, 'error' => '服务商不存在'], 422);
            }
        }

        foreach ($roleCodes as $code) {
            $role = $roleRepo->findOneBy(['code' => $code]);
            if ($role) {
                $role->setProvider($provider);
            }
        }
        $em->flush();

        $msg = $provider
            ? '已为 ' . count($roleCodes) . ' 个角色绑定服务商：' . $provider->getName()
            : '已解除 ' . count($roleCodes) . ' 个角色的绑定';

        return $this->json(['success' => true, 'message' => $msg]);
    }

    #[Route('/provider/{id}/test', name: 'admin_llm_provider_test', methods: ['POST'])]
    public function testProvider(
        LlmProvider $provider,
        LlmEncryptor $encryptor,
        LlmGatewayFactory $gatewayFactory
    ): JsonResponse {
        if (!$provider->getApiKeyEncrypted()) {
            return $this->json([
                'success' => false,
                'error' => 'API Key 未配置',
            ], 422);
        }

        try {
            $gateway = $gatewayFactory->create($provider);
            $result = $gateway->testConnection();

            return $this->json([
                'success' => true,
                'elapsed' => $result['elapsed'],
                'reply' => $result['reply'],
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    private function buildOptions(\Symfony\Component\Form\FormInterface $form): array
    {
        $options = [];
        $thinkingEnabled = (bool) $form->get('thinkingEnabled')->getData();

        $options['thinking'] = [
            'type' => $thinkingEnabled ? 'enabled' : 'disabled',
        ];

        if ($thinkingEnabled && ($effort = $form->get('reasoningEffort')->getData())) {
            $options['reasoning_effort'] = $effort;
        }

        return $options;
    }

    private function prefillThinkingOptions(\Symfony\Component\Form\FormInterface $form, LlmProvider $provider): void
    {
        $opts = $provider->getOptions() ?? [];
        $thinking = $opts['thinking']['type'] ?? 'enabled';
        $form->get('thinkingEnabled')->setData($thinking !== 'disabled');
        $form->get('reasoningEffort')->setData($opts['reasoning_effort'] ?? null);
    }
}
