<?php

namespace App\Command;

use App\Entity\System\EmailTemplate;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * 邮件模板多语言种子 / Seed multi-language email templates.
 *
 * 为内置邮件模板提供 zh_CN / en 双语内容（验证码通知、重置密码）。
 * Provides bilingual (zh_CN / en) content for built-in email templates.
 */
#[AsCommand(
    name: 'ef:seed-email-templates',
    description: '初始化邮件模板多语言版本 / Seed localized email templates',
)]
class EfSeedEmailTemplatesCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $repo = $this->em->getRepository(EmailTemplate::class);

        $templates = [
            '验证码通知' => [
                'zh_CN' => [
                    'subject' => '验证码通知',
                    'body' => '<p style="text-align: center;"><img src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI2NCIgaGVpZ2h0PSI2NCIgdmlld0JveD0iMCAwIDY0IDY0Ij48Y2lyY2xlIGN4PSIzMiIgY3k9IjMyIiByPSIzMiIgZmlsbD0iI2UwZTdmZiIvPjxwYXRoIGQ9Ik0xNiAyMkwzMiAzNEw0OCAyMk0xNiA0Mkg0OFYyMkgxNlY0MloiIGZpbGw9Im5vbmUiIHN0cm9rZT0iIzRmNDZlNSIgc3Ryb2tlLXdpZHRoPSIyIiBzdHJva2UtbGluZWNhcD0icm91bmQiIHN0cm9rZS1saW5lam9pbj0icm91bmQiLz48L3N2Zz4=" width="64" height="64" alt="icon"></p><h2 style="text-align: center;">验证您的邮箱</h2><p style="text-align: center;"><span style="color: rgb(75, 85, 99);">感谢您注册！请使用以下一次性验证码完成邮箱验证。</span></p><p style="text-align: center;"><span style="font-family: monospace; font-size: 32px; color: rgb(31, 41, 55); background-color: rgb(243, 244, 246);"><strong> &nbsp;{{ code }}&nbsp; </strong></span></p><p style="text-align: center;"><span style="font-size: 13px; color: rgb(107, 114, 128);">安全提示：此验证码在 {{ expire_minutes }} 分钟内有效，请勿将验证码透露给他人。</span></p>',
                ],
                'en' => [
                    'subject' => 'Verification Code',
                    'body' => '<p style="text-align: center;"><img src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI2NCIgaGVpZ2h0PSI2NCIgdmlld0JveD0iMCAwIDY0IDY0Ij48Y2lyY2xlIGN4PSIzMiIgY3k9IjMyIiByPSIzMiIgZmlsbD0iI2UwZTdmZiIvPjxwYXRoIGQ9Ik0xNiAyMkwzMiAzNEw0OCAyMk0xNiA0Mkg0OFYyMkgxNlY0MloiIGZpbGw9Im5vbmUiIHN0cm9rZT0iIzRmNDZlNSIgc3Ryb2tlLXdpZHRoPSIyIiBzdHJva2UtbGluZWNhcD0icm91bmQiIHN0cm9rZS1saW5lam9pbj0icm91bmQiLz48L3N2Zz4=" width="64" height="64" alt="icon"></p><h2 style="text-align: center;">Verify Your Email</h2><p style="text-align: center;"><span style="color: rgb(75, 85, 99);">Thank you for registering! Use the one-time code below to verify your email.</span></p><p style="text-align: center;"><span style="font-family: monospace; font-size: 32px; color: rgb(31, 41, 55); background-color: rgb(243, 244, 246);"><strong> &nbsp;{{ code }}&nbsp; </strong></span></p><p style="text-align: center;"><span style="font-size: 13px; color: rgb(107, 114, 128);">Security note: this code is valid for {{ expire_minutes }} minutes. Do not share it with anyone.</span></p>',
                ],
            ],
            '重置密码' => [
                'zh_CN' => [
                    'subject' => '重置您的密码',
                    'body' => '<h2 style="text-align: center;">重置您的密码</h2><p style="text-align: center;"><span style="color: rgb(75, 85, 99);">我们收到了重置您账户密码的请求。如果您确实发起了此请求，请点击下方链接重置密码。</span></p><p style="text-align: center;"><a href="{{ reset_url }}" style="background-color: rgb(79, 70, 229); color: #ffffff; padding: 12px 24px; border-radius: 8px; text-decoration: none; display: inline-block;">重置密码</a></p><p style="text-align: center;"><span style="font-size: 13px; color: rgb(107, 114, 128);">如果您没有请求重置密码，您可以安全地忽略此邮件，您的密码不会被更改。</span></p>',
                ],
                'en' => [
                    'subject' => 'Reset Your Password',
                    'body' => '<h2 style="text-align: center;">Reset Your Password</h2><p style="text-align: center;"><span style="color: rgb(75, 85, 99);">We received a request to reset your account password. If you made this request, click the link below to reset it.</span></p><p style="text-align: center;"><a href="{{ reset_url }}" style="background-color: rgb(79, 70, 229); color: #ffffff; padding: 12px 24px; border-radius: 8px; text-decoration: none; display: inline-block;">Reset Password</a></p><p style="text-align: center;"><span style="font-size: 13px; color: rgb(107, 114, 128);">If you did not request a password reset, you can safely ignore this email. Your password will not be changed.</span></p>',
                ],
            ],
        ];

        $count = 0;
        foreach ($templates as $name => $locales) {
            foreach ($locales as $locale => $content) {
                $exists = $repo->findOneBy(['name' => $name, 'locale' => $locale]);
                if ($exists) {
                    continue;
                }
                $template = new EmailTemplate();
                $template->setCode($name);
                $template->setName($name);
                $template->setLocale($locale);
                $template->setSubject($content['subject']);
                $template->setBodyHtml($content['body']);
                $this->em->persist($template);
                $count++;
            }
        }
        $this->em->flush();

        $io->success(sprintf('邮件模板多语言已就绪 / Localized email templates seeded: +%d', $count));

        return Command::SUCCESS;
    }
}
