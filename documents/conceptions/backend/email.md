# 邮件服务架构与配置中心设计

## 1. 架构目标与定位

随着系统业务的扩展（如：重置密码、新用户欢迎、通知告警等），邮件发送是一个高频的基础设施能力。为了避免在各个业务模块（如“安全配置-重置密码”）中散落、硬编码邮件发送逻辑和 SMTP 配置，系统需要基于 `symfony/mailer` 构建一个**统一的邮件配置与模版管理中心**。

**设计原则 (遵循 SOLID、DRY、KISS)**:

- **集中管理**：所有的发件服务器配置（SMTP/API）统一在“系统设置 -> 邮件配置”中维护。
- **模板解耦**：业务代码只负责触发“发送动作”并提供“变量数据”，邮件的主题、内容（支持多语言）由独立的可视化模板管理。
- **动态构造**：废弃 `.env` 中的硬编码 `MAILER_DSN`，改为从数据库动态读取配置并实例化 `Transport`。
- **异步处理**：集成 Symfony Messenger 进行邮件的异步发送，避免阻塞主业务流程（如用户注册、密码重置）。

---

## 2. 核心功能模块划分

### 2.1 全局邮箱服务器配置 (Email Transport Config)

作为邮件发送的底层引擎。系统设置中应提供以下表单：

- **发件协议/驱动 (Transport)**：支持 `SMTP` (默认)、`Sendgrid`、`Mailgun`、`Aliyun` 等（基于 Symfony Mailer 的 Transport 工厂扩展）。
- **主机与端口 (Host & Port)**：如 `smtp.exmail.qq.com`, `465`。
- **加密方式 (Encryption)**：`SSL`, `TLS`, `None`。
- **认证信息 (Credentials)**：`Username` (通常为邮箱地址), `Password` (或授权码，**需加密存储**)。
- **发件人信息 (Sender)**：默认发件人名称 (From Name)、默认发件人地址 (From Address)。
- **操作项**：**发送测试邮件**（非常重要，用于管理员保存配置前验证连通性）。

### 2.2 邮件模版管理 (Email Template Management)

为不同的业务场景提供丰富的内容管理能力，避免开发人员介入修改代码来调整文案。

- **模版标识 (Template Code)**：如 `SECURITY_RESET_PASSWORD_EMAIL`，供业务代码调用。
- **模版名称 (Template Name)**：如“密码重置验证邮件”。
- **多语言支持 (i18n)**：支持为同一个 Code 配置不同语言版本（如 `en`, `zh_CN`）的主题和内容。
- **邮件主题 (Subject)**：支持变量替换，如 `[{{ system_name }}] 您的验证码是 {{ code }}`。
- **邮件正文 (Body)**：
  - 支持富文本编辑器 (HTML)。
  - 基于 Twig 引擎渲染，支持条件判断和循环。
  - **变量占位符字典**：在管理界面提示当前模版可用的变量（如 `{{ user.email }}`, `{{ code }}`, `{{ reset_link }}`）。

### 2.3 邮件发送日志与审计 (Email Logs)

用于排查邮件发送失败问题及安全审计。

- **记录字段**：发送时间、收件人、使用的模版、邮件主题、发送状态（成功/失败/队列中）、失败原因（Exception Message）。

---

## 3. 业务场景集成设计（以“安全配置-重置密码”为例）

在“安全配置 -> 重置密码规则 -> Email 验证方式”的弹窗中，**不应该**让用户去填写 SMTP 服务器地址和密码。

**合理的弹窗配置项应为：**

1. **开关状态 (Enable/Disable)**：是否启用邮箱找回密码。
2. **选择邮件模版 (Select Template)**：下拉单选框，从“邮件模版管理”中选择一个对应的模版（默认选中 `SECURITY_RESET_PASSWORD_EMAIL`）。
3. **验证码有效期 (Expiration Time)**：如 15 分钟。
4. **提示链接**：提供一个超链接：“如需修改发件服务器，请前往 `[系统设置 - 邮件配置]`”。

_这样设计保证了配置的单一真实来源（Single Source of Truth），并且让安全模块专注于安全策略本身，而不是发件底层技术。_

---

## 4. 底层技术实现方案

### 4.1 动态 Transport 构造 (Dynamic Mailer DSN)

Symfony 默认通过环境变量 `MAILER_DSN` 实例化 `MailerInterface`。我们需要自定义一个 `MailerFactory` 或 Compiler Pass，在运行时从数据库（如 `SystemConfig` 或独立的 `EmailConfig` 表）读取配置，动态构建 DSN 字符串，并使用 `Transport::fromDsn()` 创建 `TransportInterface`，最终注入到自定义的 `DynamicMailer` 服务中。

```php
// 示例伪代码：动态构建 Transport
$dsn = sprintf(
    '%s://%s:%s@%s:%d',
    $config->getProtocol(), // smtp
    urlencode($config->getUsername()),
    urlencode($this->decrypt($config->getPassword())), // 解密密码
    $config->getHost(),
    $config->getPort()
);
$transport = Transport::fromDsn($dsn);
$mailer = new Mailer($transport, $bus, $eventDispatcher);
```

### 4.2 统一的 MailService 门面 (Facade)

提供一个唯一的入口服务，供 Controller 或其他 Service 调用。

```php
interface MailServiceInterface {
    /**
     * @param string $to 收件人邮箱
     * @param string $templateCode 模版标识 (如 'SECURITY_RESET_PASSWORD_EMAIL')
     * @param array $context 模版变量字典 (如 ['code' => '123456', 'user' => $user])
     * @param string|null $locale 指定语言（为空则取当前用户或系统默认语言）
     */
    public function send(string $to, string $templateCode, array $context = [], ?string $locale = null): void;
}
```

**内部执行逻辑**：

1. 根据 `$templateCode` 和 `$locale` 查询数据库获取模版的主题和 HTML 正文。
2. 使用 Twig Environment 渲染主题和正文。
3. 构建 `Symfony\Component\Mime\Email` 对象。
4. 调用 `DynamicMailer->send($email)` （结合 Messenger 实现异步）。
5. 记录发送日志到数据库。

---

## 5. 数据库设计草案 (Schema)

- **`sys_email_config`** (单例表，或集成在全局配置字典中)：存储 SMTP 服务器相关信息。
- **`sys_email_template`**：
  - `id`
  - `code` (VARCHAR, UNIQUE) - 模版标识
  - `name` (VARCHAR) - 模版说明名称
  - `description` (TEXT) - 可用变量说明
- **`sys_email_template_translation`** (多语言表)：
  - `id`, `translatable_id`, `locale`
  - `subject` (VARCHAR) - 邮件主题 (Twig 语法)
  - `body_html` (LONGTEXT) - 邮件正文 (Twig 语法)
- **`sys_email_log`**：
  - `id`
  - `recipient` (VARCHAR)
  - `template_code` (VARCHAR)
  - `subject` (VARCHAR)
  - `status` (ENUM: pending, success, failed)
  - `error_message` (TEXT)
  - `created_at` (DATETIME)
  - `sent_at` (DATETIME)
