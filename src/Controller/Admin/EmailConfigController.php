<?php

namespace App\Controller\Admin;

use App\Entity\System\EmailConfig;
use App\Entity\System\EmailTemplate;
use App\Entity\System\EmailFunctionBinding;
use App\Repository\System\EmailConfigRepository;
use App\Repository\System\EmailTemplateRepository;
use App\Repository\System\EmailFunctionBindingRepository;
use App\Service\Security\EmailHtmlSanitizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin/email')]
#[IsGranted('ROLE_ADMIN')]
class EmailConfigController extends AbstractController
{
    public function __construct(
        private readonly EmailHtmlSanitizer $emailHtmlSanitizer,
        private readonly TranslatorInterface $translator
    ) {
    }

    public const SYSTEM_EMAIL_FUNCTIONS = [
        'security.reset_password' => '重置密码验证邮件',
        'employee.verification' => '员工邮箱验证邮件',
    ];

    #[Route('/', name: 'admin_email_index')]
    public function index(EmailConfigRepository $configRepository, EmailTemplateRepository $templateRepository, EmailFunctionBindingRepository $bindingRepository, EntityManagerInterface $em): Response
    {
        $configs = $configRepository->findAll();
        $templates = $templateRepository->findBy([], ['createdAt' => 'DESC']);

        // Auto-initialize missing bindings
        $flushNeeded = false;
        foreach (self::SYSTEM_EMAIL_FUNCTIONS as $code => $name) {
            $binding = $bindingRepository->findOneBy(['functionCode' => $code]);
            if (!$binding) {
                $binding = new EmailFunctionBinding();
                $binding->setFunctionCode($code);
                $binding->setFunctionName($name);
                $em->persist($binding);
                $flushNeeded = true;
            } else if ($binding->getFunctionName() !== $name) {
                $binding->setFunctionName($name);
                $flushNeeded = true;
            }
        }
        if ($flushNeeded) {
            $em->flush();
        }

        $bindings = $bindingRepository->findAll();

        return $this->render('admin/email/index.html.twig', [
            'configs' => $configs,
            'templates' => $templates,
            'bindings' => $bindings,
        ]);
    }

    #[Route('/template/editor', name: 'admin_email_template_editor')]
    #[Route('/template/editor/{id}', name: 'admin_email_template_editor_with_id')]
    public function editor(Request $request, EmailConfigRepository $configRepository, EmailTemplateRepository $templateRepository, ?EmailTemplate $id = null): Response
    {
        $configs = $configRepository->findAll();

        // 邮件模板多语言：默认当前请求语言，可经 ?locale= 显式切换 /
        // Multi-language email templates: default to request locale, switchable via ?locale=
        $locale = $request->query->get('locale') ?: $request->getLocale();
        $locale = in_array($locale, ['zh_CN', 'en'], true) ? $locale : 'zh_CN';

        $templates = $templateRepository->findBy(['locale' => $locale], ['createdAt' => 'DESC']);
        if (empty($templates)) {
            // 该语言尚无模板时回退默认语言 / Fall back to default locale when empty
            $templates = $templateRepository->findBy(['locale' => 'zh_CN'], ['createdAt' => 'DESC']);
        }

        return $this->render('admin/email/editor.html.twig', [
            'configs' => $configs,
            'templates' => $templates,
            'editingTemplate' => $id,
            'currentLocale' => $locale,
            'supportedLocales' => ['zh_CN' => '中文', 'en' => 'English'],
        ]);
    }

    #[Route('/template/test-send', name: 'admin_email_template_test_send', methods: ['POST'])]
    public function testTemplateSend(Request $request, EmailConfigRepository $configRepository, \Twig\Environment $twig): Response
    {
        $content = $request->getContent();
        $data = !empty($content) ? json_decode($content, true) : [];
        if (!is_array($data)) {
            $data = [];
        }

        $subject = $data['subject'] ?? $request->request->get('subject');
        $bodyHtml = $data['bodyHtml'] ?? $request->request->get('bodyHtml');
        $emailConfigId = $data['emailConfigId'] ?? $request->request->get('emailConfigId');
        $testEmail = $data['testEmail'] ?? $request->request->get('testEmail');

        if (!$testEmail) {
            return \App\Controller\Api\ApiResponse::error(json_encode([]), 400, 'msg.email.recipient_required');
        }

        $config = null;
        if ($emailConfigId) {
            $config = $configRepository->find($emailConfigId);
        }
        if (!$config) {
            $config = $configRepository->findOneBy(['isDefault' => true]);
        }

        if (!$config) {
            return \App\Controller\Api\ApiResponse::error(json_encode([]), 400, 'msg.email.no_config');
        }

        try {
            $dsn = sprintf('%s://', $config->getProtocol());
            if ($config->getUsername()) {
                $dsn .= urlencode($config->getUsername());
                if ($config->getPassword()) {
                    $dsn .= ':' . urlencode($config->getPassword());
                }
                $dsn .= '@';
            }
            $dsn .= $config->getHost() . ':' . $config->getPort();

            $transport = \Symfony\Component\Mailer\Transport::fromDsn($dsn);
            $mailer = new \Symfony\Component\Mailer\Mailer($transport);

            // Unmangle Twig tags
            $unmangle = function($text) {
                return preg_replace_callback('/\{\{(.*?)\}\}/', function($matches) {
                    return '{{' . urldecode($matches[1]) . '}}';
                }, $text ?: '');
            };
            $subject = $unmangle($subject);
            $bodyHtml = $unmangle($bodyHtml);

            // Render twig if it contains variables (dummy data for testing)
            try {
                $renderedSubject = $twig->createTemplate($subject ?: '')->render(['code' => '123456', 'user.email' => 'test@example.com']);
                $renderedBody = $twig->createTemplate($bodyHtml ?: '')->render(['code' => '123456', 'user.email' => 'test@example.com', 'username' => 'john.doe', 'login_url' => 'https://example.com/login', 'reset_url' => 'https://example.com/reset', 'announcement_title' => '系统通知', 'announcement_body' => '系统将于今晚升级']);
            } catch (\Throwable $e) {
                // If twig rendering fails (e.g. syntax error in template), fallback to original
                $renderedSubject = $subject;
                $renderedBody = $bodyHtml;
            }

            // Wrap the body with standard email layout only if it doesn't already have it
            $emailLayout = $this->emailHtmlSanitizer->sanitize($renderedBody) ?: '<p>Empty Body</p>';
            if (strpos($emailLayout, 'id="ef-email-wrapper"') === false) {
                $emailWrapperStart = '<div id="ef-email-wrapper" style="background-color: #f4f5f7; padding: 40px 20px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif, \'Apple Color Emoji\', \'Segoe UI Emoji\', \'Segoe UI Symbol\'; color: #333333;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" align="center" style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); overflow: hidden;"><tr><td style="height: 6px; background: linear-gradient(90deg, #2563eb 0%, #4f46e5 100%);"></td></tr><tr><td style="padding: 40px;">';
                $emailWrapperEnd = '</td></tr><tr><td style="padding: 30px 40px; background-color: #f9f9f9; text-align: center; font-size: 12px; color: #999999; border-top: 1px solid #eeeeee;"><p style="margin: 0 0 10px 0;">© 2025 Your Company Name. All rights reserved.</p><p style="margin: 0;">此邮件为系统自动发送，请勿直接回复。</p></td></tr></table></div>';
                $emailLayout = $emailWrapperStart . $emailLayout . $emailWrapperEnd;
            }

            $email = (new \Symfony\Component\Mime\Email())
                ->from(new \Symfony\Component\Mime\Address($config->getSenderAddress(), $config->getSenderName() ?: ''))
                ->to($testEmail)
                ->subject($renderedSubject ?: 'Test Template Email')
                ->html($emailLayout);

            $mailer->send($email);

            return \App\Controller\Api\ApiResponse::success(json_encode([]), 200, '测试邮件发送成功，已发送至 ' . $testEmail);
        } catch (\Exception $e) {
            return \App\Controller\Api\ApiResponse::error(json_encode([]), 500, '发送失败，请检查邮件服务器配置和模板内容。');
        }
    }

    #[Route('/config/save', name: 'admin_email_config_save', methods: ['POST'])]
    public function saveConfig(Request $request, EntityManagerInterface $em, EmailConfigRepository $configRepository): Response
    {
        $id = $request->request->get('id');
        if ($id) {
            $config = $configRepository->find($id);
            if (!$config) {
                throw $this->createNotFoundException('Config not found');
            }
        } else {
            $config = new EmailConfig();
            $em->persist($config);
        }

        $config->setName($request->request->get('name'));
        $isDefault = $request->request->get('isDefault') === '1';
        $config->setIsDefault($isDefault);

        if ($isDefault) {
            // Unset default for all others
            $otherConfigs = $configRepository->findAll();
            foreach ($otherConfigs as $other) {
                if ($other !== $config) {
                    $other->setIsDefault(false);
                }
            }
        } else {
            // If there are no configs, make this one default
            $count = $configRepository->count([]);
            if ($count === 0 || ($count === 1 && $config->getId() !== null)) {
                $config->setIsDefault(true);
            }
        }

        $config->setProtocol($request->request->get('protocol', 'smtp'));
        $config->setHost($request->request->get('host'));
        $config->setPort((int) $request->request->get('port', 465));
        $config->setEncryption($request->request->get('encryption', 'ssl'));
        $config->setUsername($request->request->get('username'));
        
        $password = $request->request->get('password');
        if (!empty($password)) {
            $config->setPassword($password);
        }

        $config->setSenderName($request->request->get('senderName'));
        $config->setSenderAddress($request->request->get('senderAddress'));

        $em->flush();

        $this->addFlash('success', $this->translator->trans('admin_email.flash.config_saved'));

        return $this->redirectToRoute('admin_email_index');
    }

    #[Route('/config/delete/{id}', name: 'admin_email_config_delete', methods: ['POST'])]
    public function deleteConfig(EmailConfig $config, EntityManagerInterface $em): Response
    {
        $em->remove($config);
        $em->flush();

        $this->addFlash('success', $this->translator->trans('admin_email.flash.config_deleted'));

        return $this->redirectToRoute('admin_email_index');
    }

    #[Route('/binding/save', name: 'admin_email_binding_save', methods: ['POST'])]
    public function saveBinding(Request $request, EntityManagerInterface $em, EmailFunctionBindingRepository $bindingRepository, EmailConfigRepository $configRepository, EmailTemplateRepository $templateRepository): Response
    {
        $id = $request->request->get('id');
        $binding = $bindingRepository->find($id);
        
        if (!$binding) {
            throw $this->createNotFoundException('Binding not found');
        }

        $configId = $request->request->get('emailConfigId');
        if ($configId) {
            $config = $configRepository->find($configId);
            $binding->setEmailConfig($config);
        } else {
            $binding->setEmailConfig(null);
        }

        $templateId = $request->request->get('emailTemplateId');
        if ($templateId) {
            $template = $templateRepository->find($templateId);
            $binding->setEmailTemplate($template);
        } else {
            $binding->setEmailTemplate(null);
        }

        $em->flush();
        
        $this->addFlash('success', $this->translator->trans('admin_email.flash.binding_saved'));

        return $this->redirectToRoute('admin_email_index', ['tab' => 'bindings']);
    }

    #[Route('/template/save', name: 'admin_email_template_save', methods: ['POST'])]
    public function saveTemplate(Request $request, EntityManagerInterface $em, EmailTemplateRepository $templateRepository, EmailConfigRepository $configRepository): Response
    {
        $id = $request->request->get('id');
        if ($id) {
            $template = $templateRepository->find($id);
            if (!$template) {
                throw $this->createNotFoundException('Template not found');
            }
        } else {
            $template = new EmailTemplate();
            $em->persist($template);
        }

        $name = $request->request->get('name');
        
        if (empty($name)) {
            $this->addFlash('error', 'flash.template_name_required');
            return $this->redirectToRoute('admin_email_index');
        }

        // 模板语言：随表单提交，缺省当前请求语言 / Template locale from form, default to request locale
        $locale = (string) $request->request->get('locale');
        if (!in_array($locale, ['zh_CN', 'en'], true)) {
            $locale = $request->getLocale();
        }
        $locale = in_array($locale, ['zh_CN', 'en'], true) ? $locale : 'zh_CN';

        $existing = $templateRepository->findOneBy(['name' => $name, 'locale' => $locale]);
        if ($existing && $existing->getId() != $id) {
            $this->addFlash('error', 'flash.template_name_duplicate');
            return $this->redirectToRoute('admin_email_index');
        }

        $bodyHtmlRaw = (string) $request->request->get('bodyHtml');
        $bodyHtml = $this->emailHtmlSanitizer->sanitize($bodyHtmlRaw);

        // Unmangle Twig tags that might have been URL-encoded by DOMDocument in sanitizer (e.g. {{%20var%20}})
        $unmangle = function($text) {
            return preg_replace_callback('/\{\{(.*?)\}\}/', function($matches) {
                return '{{' . urldecode($matches[1]) . '}}';
            }, $text ?: '');
        };
        $bodyHtml = $unmangle($bodyHtml);

        $template->setCode($name);
        $template->setName($name);
        $template->setLocale($locale);
        $template->setSubject($unmangle($request->request->get('subject')));
        $template->setBodyHtml($bodyHtml);
        $template->setDescription($request->request->get('description'));

        $configId = $request->request->get('emailConfigId');
        if ($configId) {
            $emailConfig = $configRepository->find($configId);
            $template->setEmailConfig($emailConfig);
        } else {
            $template->setEmailConfig(null);
        }

        $em->flush();

        $this->addFlash('success', $this->translator->trans('admin_email.flash.template_saved'));

        return $this->redirectToRoute('admin_email_index', ['tab' => 'templates']);
    }

    #[Route('/template/delete/{id}', name: 'admin_email_template_delete', methods: ['POST'])]
    public function deleteTemplate(EmailTemplate $template, EntityManagerInterface $em): Response
    {
        $em->remove($template);
        $em->flush();

        $this->addFlash('success', $this->translator->trans('admin_email.flash.template_deleted'));

        return $this->redirectToRoute('admin_email_index', ['tab' => 'templates']);
    }

    #[Route('/template/preview', name: 'admin_email_template_preview', methods: ['POST'])]
    public function previewTemplate(Request $request): Response
    {
        $html = $this->emailHtmlSanitizer->sanitize($request->request->get('html'));
        
        // Unmangle Twig tags
        $html = preg_replace_callback('/\{\{(.*?)\}\}/', function($matches) {
            return '{{' . urldecode($matches[1]) . '}}';
        }, $html ?: '');

        $subject = $request->request->get('subject', 'No Subject');
        
        return $this->render('admin/email/preview.html.twig', [
            'html' => $html,
            'subject' => $subject,
        ]);
    }

    #[Route('/config/test-connection', name: 'admin_email_config_test_connection', methods: ['POST'])]
    public function testConnection(Request $request, EmailConfigRepository $configRepository): Response
    {
        $content = $request->getContent();
        $data = !empty($content) ? json_decode($content, true) : [];
        if (!is_array($data)) {
            $data = [];
        }

        $id = $data['id'] ?? $request->request->get('id');
        $protocol = $data['protocol'] ?? $request->request->get('protocol', 'smtp');
        $host = $data['host'] ?? $request->request->get('host');
        $port = (int) ($data['port'] ?? $request->request->get('port', 465));
        $username = $data['username'] ?? $request->request->get('username');
        $password = $data['password'] ?? $request->request->get('password');
        $senderAddress = $data['senderAddress'] ?? $request->request->get('senderAddress');
        $senderName = $data['senderName'] ?? $request->request->get('senderName');
        $testEmail = $data['testEmail'] ?? $request->request->get('testEmail') ?: $senderAddress;

        if (!$password && $id) {
            $config = $configRepository->find($id);
            if ($config) {
                $password = $config->getPassword();
            }
        }

        try {
            $dsn = sprintf('%s://', $protocol);
            if ($username) {
                $dsn .= urlencode($username);
                if ($password) {
                    $dsn .= ':' . urlencode($password);
                }
                $dsn .= '@';
            }
            $dsn .= $host . ':' . $port;

            $transport = \Symfony\Component\Mailer\Transport::fromDsn($dsn);
            $mailer = new \Symfony\Component\Mailer\Mailer($transport);

            $email = (new \Symfony\Component\Mime\Email())
                ->from(new \Symfony\Component\Mime\Address($senderAddress, $senderName ?: ''))
                ->to($testEmail) // Send test email to the provided test email
                ->subject('Test Connection from Enterprise Framework')
                ->text('这是一封用于验证邮件服务器配置的测试邮件。This is a test email to verify the server configuration.');

            $mailer->send($email);

            return \App\Controller\Api\ApiResponse::success(json_encode([]), 200, '连接成功，测试邮件已发送至 ' . $testEmail);
        } catch (\Exception $e) {
            return \App\Controller\Api\ApiResponse::error(json_encode([]), 500, 'msg.email.conn_failed');
        }
    }
}
