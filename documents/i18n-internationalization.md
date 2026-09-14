# Internationalization (i18n) / 国际化

> English | [中文](i18n-internationalization.zh-CN.md)

## Goals / 目标

Support **multi-language, multi-country, multi-currency** for the whole system:

- 多语言：界面文案可随语言切换（zh_CN / en，可扩展）
- 多国家：组织/公司可关联国家，驱动默认语言、币种、电话区号、时区
- 多币种：金额以「数值 + 币种」存储与格式化
- 源码注释中英双语、文档中英双版本

## Current Status / 现状（Phase 0 已完成）

| Area | Status |
|---|---|
| Symfony 翻译 / Translation | ✅ `translations/messages.zh_CN.yaml` + `.en.yaml`，`APP_LOCALE=zh_CN` |
| 语言切换 / Locale switch | ✅ 顶栏下拉 + `LocaleSubscriber`（白名单校验 + session） |
| 时区 / Timezone | ✅ `APP_TIMEZONE=Asia/Shanghai` + `date.timezone`（Caddyfile php_ini） |
| 币种/国家参考数据 | ✅ `platform_currency`(294)、`platform_country`(249)，`ef:seed-localization` 生成 |
| 金额格式化 / Money | ✅ `Money` 值对象 + `MoneyFormatter`（Intl NumberFormatter） |
| 组织多国字段 | ✅ Company/Corporation 增加 `countryCode/defaultLocale/defaultCurrency` |
| JS 前端 i18n | ✅ `i18n/translator.js`（`window.t()`）+ `window.__I18N__` 按语言注入 |

## Architecture / 架构

- **服务端文案**：模板 `{{ 'key'|trans }}`，PHP 消息 `$translator->trans()`，key 存入双语 `messages.*.yaml`
- **前端文案**：服务端 `TwigExtension::getJsMessages()` 导出当前语言的 `js.*`/`toolbar.*` 字典 → `window.__I18N__` → `t('key')`
- **金额**：`Money(amount, currency)` → `MoneyFormatter::format()`（`NumberFormatter::CURRENCY`，按 locale/币种）
- **多国**：`Country(code, name, locale, currencyCode, phoneCode, timezone)`；组织实体的 `countryCode` 驱动默认本地化参数

## Transformation Roadmap / 改造路线

- **Phase 0 — 基础设施** ✅ 完成（见上）
- **Phase 1 — 界面文案国际化** 🟡 进行中
  - 生产模板（`templates/admin/**`，不含 `test/`）硬编码中文 → `|trans`
  - JS 字符串 → `t()`
  - PHP flash/校验消息 → trans
- **Phase 2 — 源码双语注释** 🟡 进行中
  - 实体 docblock：`/** 中文 / English */`
  - Service/Command/Controller 关键英文注释
- **Phase 3 — 文档双语** 🟡 进行中
  - 每个 `.md` 提供英文版（`X.md`）+ 中文版（`X.zh-CN.md`）

## Email Template Multi-Language / 邮件模板多语言

Email templates support multiple languages:

- `sys_email_template` gained a `locale` column; unique index is `(code, locale)`.
- The email editor has a **language switcher** (default = user locale, freely switchable via `?locale=`).
- Templates are listed/filtered per locale; saving writes the current editor locale.
- `MailService::send`/`sendForFunction` resolve the template by the recipient/request locale with fallback to `zh_CN`.
- Seed: `php bin/console ef:seed-email-templates` (bilingual verification + password-reset templates).

## Commands / 命令

```bash
# 初始化/补充币种与国家参考数据
php bin/console ef:seed-localization

# 迁移
php bin/console doctrine:migrations:migrate
```

## Conventions / 约定

- 新增界面文案必须成对写入 `messages.zh_CN.yaml` 与 `messages.en.yaml`
- 内部标识（`@Ef` 分组名、字段名、代码键）保持英文/拼音不变，仅人类可读文本双语
- JS 文案统一走 `t()`，不硬编码中文
