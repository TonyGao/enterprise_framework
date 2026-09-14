<?php

namespace App\EventSubscriber;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * 国际化/本地化请求监听器。
 *
 * Internationalization/Locale request listener.
 *
 * 职责 / Responsibilities:
 * - 依据 ?_locale= 查询参数或 session 设置当前请求语言
 *   Set the request locale from the ?_locale= query parameter or session.
 * - 白名单校验，防止注入任意 locale（多语言安全边界）
 *   Validate against a whitelist to prevent arbitrary locale injection.
 * - 依据应用时区初始化 PHP 日期时区（多时区支持的基础）
 *   Initialize PHP date timezone from the app timezone (multi-timezone foundation).
 */
class LocaleSubscriber implements EventSubscriberInterface
{
    /** @param string[] $supportedLocales 支持的语言列表 / Supported locales */
    public function __construct(
        #[Autowire(param: 'app.supported_locales')]
        private readonly array $supportedLocales,
        #[Autowire('%env(APP_TIMEZONE)%')]
        private readonly string $timezone,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest()) {
            return;
        }

        // 时区：以应用配置为准 / Timezone from app config
        if ($this->timezone) {
            date_default_timezone_set($this->timezone);
        }

        // 1. 查询参数优先 / Priority to query parameter
        if ($locale = $request->query->get('_locale')) {
            $locale = $this->normalizeLocale($locale);
            if ($locale) {
                $request->setLocale($locale);
                try {
                    $request->getSession()->set('_locale', $locale);
                } catch (\Exception) {
                    // Session might not be enabled or started
                }
                return;
            }
        }

        // 2. 会话保存的语言 / Locale persisted in session
        try {
            if ($request->hasPreviousSession()) {
                $locale = $this->normalizeLocale((string) $request->getSession()->get('_locale', ''));
                if ($locale) {
                    $request->setLocale($locale);
                }
            }
        } catch (\Exception) {
            // Session issue
        }
    }

    /**
     * 归一化并校验 locale；非法值返回 null。
     *
     * Normalize and validate a locale; return null when invalid.
     */
    private function normalizeLocale(string $locale): ?string
    {
        $locale = trim($locale);
        if ($locale === '') {
            return null;
        }
        // 兼容 "zh"、"zh_CN"、"zh-CN" 等写法 / Tolerate "zh", "zh_CN", "zh-CN" forms
        $candidates = [$locale, str_replace('-', '_', $locale)];
        foreach ($candidates as $candidate) {
            if (in_array($candidate, $this->supportedLocales, true)) {
                return $candidate;
            }
        }
        // 前缀匹配（如 zh_CN -> zh） / Prefix match fallback
        $prefix = strtolower(explode('_', $locale)[0]);
        foreach ($this->supportedLocales as $supported) {
            if (strtolower(explode('_', $supported)[0]) === $prefix) {
                return $supported;
            }
        }
        return null;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // must be registered before (i.e. with a higher priority than) the default Locale listener
            KernelEvents::REQUEST => [['onKernelRequest', 20]],
        ];
    }
}
