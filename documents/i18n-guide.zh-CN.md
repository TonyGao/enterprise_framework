# 国际化开发指南 / Internationalization Developer Guide

> [English](i18n-guide.md) | 中文

本指南说明在新增界面文案、模板或邮件时须遵循的约定，保证系统始终可翻译（zh_CN / en，可扩展）。

This guide explains the conventions to follow when contributing UI text, templates or emails so the system stays fully translatable.

---

## 1. 服务端文案（Twig） / Server-side strings (Twig)

- 使用 `{{ 'some.key'|trans }}`——模板中禁止硬编码中文。
- key 必须**同时**写入两个文件（缺一不可）：
  - `translations/messages.zh_CN.yaml`
  - `translations/messages.en.yaml`
- key 用点号命名空间：`task.execution_time`、`toolbar.save`。
- `ef:i18n-scan` 命令会报告缺失 key 与硬编码中文。

## 2. 前端文案（JS） / Frontend strings (JS)

- 调用 `t('some.key')`——每个 admin 页（含视图编辑器布局）都会把字典注入 `window.__I18N__`。
- `t(key, {param: value})` 支持值中的 `:param` 占位符。
- API 错误：后端把翻译 key 作为 `code` 返回；前端用 `window.apiMsg(xhr)` 优先翻译 `code`，回退 `message`。
- JS 中禁止硬编码中文。

## 3. PHP 消息 / PHP messages

- Flash：`$this->addFlash('success', 'flash.xxx')`——base 布局通过 `|trans` 自动翻译 key。
- API 错误：`ApiResponse::error($content, 'your.error.key', '回退消息')`。

## 4. 邮件模板（多语言） / Email templates (multi-language)

- `sys_email_template` 按 `(code, locale)` 存一行。
- 邮件编辑器有语言切换器（默认用户 locale，可经 `?locale=` 切换）。
- 用 `php bin/console ef:seed-email-templates` 播种多语言模板。
- `MailService::send($to, $code, $ctx, $locale)` 按语言解析模板，缺失回退 `zh_CN`。

## 5. 语言 / Locales

- 支持的语言在 `config/services.yaml` → `app.supported_locales` 配置。
- 默认语言/时区：`.env` → `APP_LOCALE`、`APP_TIMEZONE`（Caddyfile 设 `date.timezone` / `intl.default_locale`）。
- 新增语言：加入 `app.supported_locales`、创建 `messages.<locale>.yaml`、扩展 admin 语言下拉。

## 6. 源码注释与文档 / Source comments & docs

- 新增源码注释尽量双语：`/** 中文 / English */`。
- 文档提供 `X.md`（英文）与 `X.zh-CN.md`（中文）。

## 验证 / Verification

```bash
php bin/console ef:i18n-scan        # 0 缺失 key；无硬编码中文
php bin/console cache:clear
```
