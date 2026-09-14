# Internationalization Developer Guide / 国际化开发指南

> [English](i18n-guide.md) | [中文](i18n-guide.zh-CN.md)

This guide explains the conventions to follow when contributing UI text, templates or emails so the system stays fully translatable (zh_CN / en, extensible).

本指南说明在新增界面文案、模板或邮件时须遵循的约定，保证系统始终可翻译（zh_CN / en，可扩展）。

---

## 1. Server-side strings (Twig) / 服务端文案（Twig）

- Use `{{ 'some.key'|trans }}` — never hardcode Chinese in templates.
- Add the key to **both** files (never only one):
  - `translations/messages.zh_CN.yaml`
  - `translations/messages.en.yaml`
- Keys use dots for namespacing: `task.execution_time`, `toolbar.save`.
- The `ef:i18n-scan` command reports missing keys and hardcoded CJK.

## 2. Frontend strings (JS) / 前端文案（JS）

- Call `t('some.key')` — the dictionary is injected into `window.__I18N__` on every admin page (and the view editor layout).
- `t(key, {param: value})` supports `:param` placeholders in the value.
- API error responses: pass a translation key as the API `code`; frontend uses `window.apiMsg(xhr)` to prefer the translated `code` over the raw `message`.
- Never hardcode Chinese in JS.

## 3. PHP messages / PHP 消息

- Flash messages: `$this->addFlash('success', 'flash.xxx')` — the base layout translates the key automatically via `|trans`.
- API errors: `ApiResponse::error($content, 'your.error.key', 'fallback message')`.

## 4. Email templates (multi-language) / 邮件模板（多语言）

- `sys_email_template` stores one row per `(code, locale)`.
- The email editor has a language switcher (default = user locale, switchable via `?locale=`).
- Seed localized templates with `php bin/console ef:seed-email-templates`.
- `MailService::send($to, $code, $ctx, $locale)` resolves the template by locale, falling back to `zh_CN`.

## 5. Locales / 语言

- Supported locales are configured in `config/services.yaml` → `app.supported_locales`.
- Default locale / timezone: `.env` → `APP_LOCALE`, `APP_TIMEZONE` (Caddyfile sets `date.timezone` / `intl.default_locale`).
- Add a new language: add the locale to `app.supported_locales`, create `messages.<locale>.yaml`, and extend the admin language dropdown.

## 6. Source comments & docs / 源码注释与文档

- New source comments should be bilingual: `/** 中文 / English */`.
- Docs: provide `X.md` (English) and `X.zh-CN.md` (Chinese).

## Verification / 验证

```bash
php bin/console ef:i18n-scan        # 0 missing keys; no hardcoded CJK
php bin/console cache:clear
```
