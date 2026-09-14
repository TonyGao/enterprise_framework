<?php

namespace App\Service;

use App\Entity\System\EmailLog;
use App\Repository\System\EmailConfigRepository;
use App\Repository\System\EmailTemplateRepository;
use App\Repository\System\EmailFunctionBindingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class MailService
{
    private EmailConfigRepository $configRepository;
    private EmailTemplateRepository $templateRepository;
    private EmailFunctionBindingRepository $bindingRepository;
    private Environment $twig;
    private EntityManagerInterface $em;
    private RequestStack $requestStack;

    public function __construct(
        EmailConfigRepository $configRepository,
        EmailTemplateRepository $templateRepository,
        EmailFunctionBindingRepository $bindingRepository,
        Environment $twig,
        EntityManagerInterface $em,
        RequestStack $requestStack,
    ) {
        $this->configRepository = $configRepository;
        $this->templateRepository = $templateRepository;
        $this->bindingRepository = $bindingRepository;
        $this->twig = $twig;
        $this->em = $em;
        $this->requestStack = $requestStack;
    }

    /**
     * Sends an email based on the configured functional binding.
     * Throws \DomainException if the function is not bound to a template.
     */
    public function sendForFunction(string $to, string $functionCode, array $context = [], ?string $locale = null): void
    {
        $binding = $this->bindingRepository->findOneBy(['functionCode' => $functionCode]);
        if (!$binding) {
            throw new \DomainException(sprintf('Email function "%s" is not initialized in the database.', $functionCode));
        }

        $template = $binding->getEmailTemplate();
        if (!$template) {
            throw new \DomainException(sprintf('Email function "%s" has no template bound to it.', $functionCode));
        }

        // 按语言解析模板：绑定模板的 code + 当前语言（缺则回退）/
        // Resolve template by locale using the bound template's code
        $locale = $locale ?: $this->currentLocale();
        $localized = $this->templateRepository->findByCodeLocalized((string) $template->getCode(), $locale);

        $config = $binding->getEmailConfig();

        $this->executeSend($to, $localized ?: $template, $config, $context);
    }

    public function send(string $to, string $templateCode, array $context = [], ?string $locale = null): void
    {
        $locale = $locale ?: $this->currentLocale();
        $template = $this->templateRepository->findByCodeLocalized($templateCode, $locale);
        if (!$template) {
            throw new \RuntimeException(sprintf('Email template "%s" not found.', $templateCode));
        }
        $config = $template->getEmailConfig();
        $this->executeSend($to, $template, $config, $context);
    }

    /**
     * 当前请求语言，无请求时回退默认语言 /
     * Current request locale, falling back to the default locale.
     */
    private function currentLocale(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request) {
            return $request->getLocale();
        }

        return 'zh_CN';
    }

    private function executeSend(string $to, \App\Entity\System\EmailTemplate $template, ?\App\Entity\System\EmailConfig $config, array $context = []): void
    {
        $log = new EmailLog();
        $log->setRecipient($to);
        $log->setTemplateCode($template->getCode() ?: 'custom');
        $log->setStatus('pending');

        try {
            if (!$config) {
                $config = $this->configRepository->findOneBy(['isDefault' => true]);
                if (!$config) {
                    $configs = $this->configRepository->findAll();
                    if (count($configs) === 0) {
                        throw new \RuntimeException('No email configuration is set.');
                    }
                    $config = $configs[0];
                }
            }

            // Render subject and body using Twig
            $subjectRaw = $template->getSubject() ?: '';
            $bodyRaw = $template->getBodyHtml() ?: '';

            // Unmangle Twig tags that might have been URL-encoded by HTML sanitizers (e.g. {{%20var%20}})
            $unmangle = function($text) {
                return preg_replace_callback('/\{\{(.*?)\}\}/', function($matches) {
                    return '{{' . urldecode($matches[1]) . '}}';
                }, $text);
            };

            $subjectRaw = $unmangle($subjectRaw);
            $bodyRaw = $unmangle($bodyRaw);

            try {
                $twigTemplateSubject = $this->twig->createTemplate($subjectRaw);
                $subject = $twigTemplateSubject->render($context);
            } catch (\Throwable $e) {
                // Fallback to raw subject if Twig rendering fails
                $subject = $subjectRaw;
            }
            $log->setSubject($subject);

            try {
                $twigTemplateBody = $this->twig->createTemplate($bodyRaw);
                $bodyHtml = $twigTemplateBody->render($context);
            } catch (\Throwable $e) {
                // Fallback to raw body if Twig rendering fails
                $bodyHtml = $bodyRaw;
            }

            // Build DSN
            $protocol = $config->getProtocol() ?: 'smtp';
            $host = $config->getHost();
            $port = $config->getPort() ?: 25;
            $username = $config->getUsername();
            $password = $config->getPassword();
            
            $dsn = sprintf('%s://', $protocol);
            if ($username) {
                $dsn .= urlencode($username);
                if ($password) {
                    $dsn .= ':' . urlencode($password);
                }
                $dsn .= '@';
            }
            $dsn .= $host . ':' . $port;

            $transport = Transport::fromDsn($dsn);
            $mailer = new Mailer($transport);

            $email = (new Email())
                ->from(new \Symfony\Component\Mime\Address($config->getSenderAddress(), $config->getSenderName() ?: ''))
                ->to($to)
                ->subject($subject)
                ->html($bodyHtml);

            $mailer->send($email);

            $log->setStatus('success');
            $log->setSentAt(new \DateTime());
        } catch (\Exception $e) {
            $log->setStatus('failed');
            $log->setErrorMessage($e->getMessage());
            throw $e;
        } finally {
            $this->em->persist($log);
            $this->em->flush();
        }
    }
}
