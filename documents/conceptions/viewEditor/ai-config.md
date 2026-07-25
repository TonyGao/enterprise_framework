# LLM 配置管理

## 1. 背景

根据 `documents/conceptions/architecture/ai.md` 的分析，Symfony AI Bundle 提供了 **Platform** 层作为 LLM 厂商的统抽象：

> *"Platform 才是真正高级的设计 — 所有模型被统一抽象，类似 Doctrine DBAL 之于数据库。"* (ai.md#四)

本方案借鉴 Symfony AI Bundle 的 Platform 设计理念，在框架内构建自有 LLM 配置管理体系，满足：

- 多模型、多厂商、多用途的灵活配置
- 图形化管理界面（非 YAML 配置）
- 运行时动态切换模型
- 为视图设计器 AI 功能（参见 `ai-view.md`）提供模型支撑

---

## 2. 菜单入口

在现有「系统设置」模块下增加二级菜单：

```
系统设置
├── 基础设置
├── 邮件配置
├── 存储配置
├── LLM 配置      ← 新增
└── ...
```

**路由**：`/admin/system/llm-config`

**权限**：`ROLE_ADMIN`

---

## 3. 数据模型

### Entity: LlmProvider

| 字段 | 类型 | 说明 |
|------|------|------|
| id | UUID | 主键 |
| name | string(100) | 显示名称，如 "GPT-4o"、"Claude 3.5" |
| provider | string(50) | 厂商标识：openai / anthropic / azure / ollama / custom |
| model | string(100) | 模型名称：gpt-4o / claude-3-5-sonnet / ... |
| apiKey | encrypted | API Key（加密存储） |
| apiEndpoint | string(255) | 接口地址（可选，用于自定义 endpoint） |
| options | JSON | 额外参数：temperature, max_tokens, top_p 等默认值 |
| isEnabled | boolean | 是否启用 |
| sortOrder | int | 排序 |
| createdAt | datetime | 创建时间 |
| updatedAt | datetime | 更新时间 |

### Entity: LlmRole

模型角色/用途定义，分离配置与用途。

| 字段 | 类型 | 说明 |
|------|------|------|
| id | UUID | 主键 |
| code | string(50) | 角色编码：view_editor / entity_generation / workflow_analysis / query_assistant / chat |
| label | string(100) | 显示名称：视图设计器 / 实体生成 / 工作流分析 / 查询助手 / 通用对话 |
| providerId | UUID → LlmProvider | 绑定的模型配置 |
| systemPrompt | text | 该角色的系统提示词 |
| options | JSON | 角色级参数覆盖（合并到 provider 默认值之上） |
| isEnabled | boolean | 是否启用 |
| updatedAt | datetime | 更新时间 |

### 角色用途规划

| 角色 code | 用途 | 推荐模型 | 说明 |
|-----------|------|----------|------|
| view_editor | 视图设计器 AI 助手（样式调整、组件生成） | gpt-4o / claude-3.5-sonnet | 需要较强的 HTML/CSS 理解和生成能力 |
| entity_generation | AI 辅助实体/模型生成 | gpt-4o-mini / claude-3-haiku | 较简单的结构化输出，可用轻量模型 |
| workflow_analysis | 工作流分析与建议 | gpt-4o | 需理解复杂业务逻辑 |
| query_assistant | 自然语言 → 数据查询 | gpt-4o-mini | 简单查询可轻量化 |
| chat | 通用 AI 对话助手 | gpt-4o-mini | 日常对话，性价比优先 |
| coding | 代码生成（Twig/JS/PHP） | gpt-4o / claude-3.5-sonnet | 需强代码能力 |

---

## 4. 管理界面

### 4.1 列表页

```
┌──────────────────────────────────────────────────────────────┐
│  LLM 配置                          [+ 新增厂商]              │
├──────────────────────────────────────────────────────────────┤
│  厂商          │  模型              │  状态  │  用途         │
│  ───────────────────────────────────────────────────────────── │
│  GPT-4o       │  gpt-4o            │  ✅ 启用 │ 视图设计器    │
│               │                    │         │ 实体生成      │
│               │                    │         │ 工作流分析    │
│  ├─ GPT-4o Mini │  gpt-4o-mini    │  ✅ 启用 │ 查询助手      │
│               │                    │         │ 通用对话      │
│  Claude 3.5   │  claude-3-5-sonnet │  ⬜ 停用 │ —            │
│  Ollama 本地  │  qwen2.5-coder    │  ✅ 启用 │ 代码生成      │
└──────────────────────────────────────────────────────────────┘
```

特点：
- 厂商卡片式展示，一个厂商可对应多个模型变体
- 直接显示绑定了该模型的角色/用途
- 开关切换启用/停用
- 拖拽排序

### 4.2 编辑表单

```
┌─────────────────────────────────────────────┐
│  编辑 LLM 配置                               │
├─────────────────────────────────────────────┤
│  名称 *        [ GPT-4o                   ] │
│  厂商 *        [ OpenAI  ▼                 ] │
│  模型 *        [ gpt-4o  ▼                 ] │
│  API Key *     [ ······················   ] │
│  接口地址      [ https://                    ] │
│                                               │
│  默认参数                                      │
│  ┌─────────────────────────────────────────┐ │
│  │ Temperature     [ 0.7      ] 0.0 - 2.0 │ │
│  │ Max Tokens      [ 4096     ]            │ │
│  │ Top P           [ 1.0      ] 0.0 - 1.0 │ │
│  └─────────────────────────────────────────┘ │
│                                               │
│  [ 保存 ]  [ 取消 ]                           │
└─────────────────────────────────────────────┘
```

### 4.3 角色绑定界面

在编辑厂商时，下方显示角色绑定区域：

```
┌─────────────────────────────────────────────┐
│  角色绑定                                      │
│  ┌─────────────────────────────────────────┐ │
│  │ ☑ 视图设计器    System Prompt: [···]  │ │
│  │ ☑ 实体生成      System Prompt: [···]  │ │
│  │ ☐ 工作流分析     System Prompt: [···]  │ │
│  │ ☑ 查询助手      System Prompt: [···]  │ │
│  │ ☐ 通用对话      System Prompt: [···]  │ │
│  └─────────────────────────────────────────┘ │
└─────────────────────────────────────────────┘
```

或反向：在角色编辑时选择绑定的厂商。

### 4.4 测试连接

编辑表单中提供「测试连接」按钮：

```
[ 测试连接 ]

> 正在调用 gpt-4o...
> 状态: ✅ 成功 (响应时间 1.2s)
> 回复: "Hello! I'm ready to help."
```

调用方式：发送一条简单的 Chat Completion 请求，验证 API Key 和模型可用性。

---

## 5. 后端架构

### 5.1 Service 层

```php
interface LlmGatewayInterface
{
    /** 根据角色获取已配置的 LLM 实例并调用 */
    public function chatByRole(string $roleCode, array $messages, array $options = []): array;
    
    /** 直接指定厂商和模型调用 */
    public function chat(string $provider, string $model, array $messages, array $options = []): array;
    
    /** 测试连接 */
    public function testConnection(string $provider, string $model, string $apiKey, ?string $endpoint): array;
    
    /** 获取可用模型列表（从厂商 API 拉取或内置列表） */
    public function getAvailableModels(string $provider): array;
}
```

### 5.2 多厂商适配器

```
LlmGatewayInterface
├── OpenAiGateway
│   ├── Chat Completion
│   └── Embedding
├── AnthropicGateway
│   ├── Messages API
│   └── Tool Use
├── AzureGateway
│   └── OpenAI 兼容 + Entra ID 认证
├── OllamaGateway
│   └── 本地 HTTP API
└── CustomGateway
    └── OpenAI 兼容格式（兼容任意 OpenAI API 镜像）
```

### 5.3 缓存策略

- `LlmProvider` 和 `LlmRole` 配置缓存至 Redis（TTL: 3600s）
- 配置变更时清除缓存
- API Key 加密存储（使用 Symfony 的 `Secrets` 机制或 `encrypt/decrypt` 服务）

### 5.4 API 路由

| 方法 | 路由 | 说明 |
|------|------|------|
| GET | `/api/admin/llm/providers` | 获取厂商列表 |
| POST | `/api/admin/llm/providers` | 新建厂商 |
| PUT | `/api/admin/llm/providers/{id}` | 编辑厂商 |
| DELETE | `/api/admin/llm/providers/{id}` | 删除厂商 |
| POST | `/api/admin/llm/providers/{id}/test` | 测试连接 |
| GET | `/api/admin/llm/models/{provider}` | 获取可用模型 |
| GET | `/api/admin/llm/roles` | 获取角色列表 |
| PUT | `/api/admin/llm/roles/{code}` | 编辑角色绑定 |

---

## 6. 前端集成

### 6.1 视图设计器中的调用

在 AI 助手面板中，通过后端代理调用 LLM：

```
用户输入 Prompt
    ↓
前端 JS → POST /api/admin/ai/chat
    ↓
后端确定 role=view_editor
    ↓
查询 LlmRole → LlmProvider（获取 API Key 等）
    ↓
调用对应 LLM API
    ↓
返回 Tool Call / 文本 → 前端执行
```

所有 API Key 仅存于服务端，前端不接触。

### 6.2 服务的动态切换

当 `view_editor` 角色绑定了多个厂商时（主/备），支持自动降级：

```
首选 → GPT-4o (gpt-4o)
        ↓ 失败（超时/限流）
备用 → Claude 3.5 (claude-3-5-sonnet)
        ↓ 失败
本地 → Ollama (qwen2.5-coder)
```

由 `LlmGateway` 服务自动处理 Failover 逻辑。

---

## 7. 安全考量

| 项目 | 方案 |
|------|------|
| API Key 存储 | 数据库加密存储（AES-256-CBC），密钥存于 `.env` / Symfony Secrets |
| 前端暴露 | 所有 LLM 调用经后端代理，前端不接触 API Key |
| 访问控制 | LLM 配置管理需 `ROLE_ADMIN`，AI 调用需 `ROLE_USER` |
| 请求审计 | 每次 AI 调用记录日志（用户、角色、耗时、Token 用量） |
| 速率限制 | 后端对 AI 调用做限流（按用户/按角色） |
| 内容安全 | AI 响应经过 XSS 过滤和 DOMPurify 清洗（用于视图设计器时） |

---

## 8. 与 Symfony AI Bundle 的关系

当前方案**不直接依赖** Symfony AI Bundle，但参考了其设计理念：

| 概念 | Symfony AI Bundle | 本项目方案 |
|------|-------------------|-----------|
| 多厂商抽象 | Platform 层 | LlmGatewayInterface + 适配器 |
| 配置来源 | YAML (`ai.yaml`) | 数据库（管理界面可编辑） |
| 模型角色 | 未明确区分 | LlmRole 实体分离配置与用途 |
| Tool Calling | Agent 系统 | 视图设计器中的 Tool Use |
| 运行时切换 | 重新配置 YAML | 数据库即时生效 |
| 适用范围 | 任意 Symfony 项目 | 本项目特有需求（视图编辑器等） |

> *"Platform — 多模型抽象，类似 Doctrine DBAL。"* (ai.md#四)

后续若 Symfony AI Bundle 生态成熟（尤其是 Agent 和 Tool Calling 部分），可考虑将底层 Gateway 替换为 Symfony AI Platform，上层业务逻辑不变。

---

## 9. 实现建议

### 第一阶段：基础 CRUD（预估 3-4 天）

#### 1.1 创建 Entity

**`src/Entity/Platform/LlmProvider.php`**

```php
#[ORM\Entity(repositoryClass: LlmProviderRepository::class)]
#[ORM\Table(name: 'platform_llm_provider')]
class LlmProvider
{
    #[ORM\Id, ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 100)]
    private string $name;

    #[ORM\Column(length: 50)]
    private string $provider;   // openai | anthropic | azure | ollama | custom

    #[ORM\Column(length: 100)]
    private string $model;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $apiKeyEncrypted = null;   // 加密存储

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $apiEndpoint = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $options = null;            // {temperature, max_tokens, top_p}

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $isEnabled = true;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $sortOrder = 0;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;
}
```

**`src/Entity/Platform/LlmRole.php`**

```php
#[ORM\Entity(repositoryClass: LlmRoleRepository::class)]
#[ORM\Table(name: 'platform_llm_role')]
class LlmRole
{
    #[ORM\Id, ORM\Column(length: 50)]
    private string $code;   // view_editor, entity_generation, ...

    #[ORM\Column(length: 100)]
    private string $label;

    #[ORM\ManyToOne(targetEntity: LlmProvider::class)]
    private ?LlmProvider $provider = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $systemPrompt = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $options = null;   // 角色级参数覆盖

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $isEnabled = true;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;
}
```

**关键设计说明：**
- `LlmProvider.apiKeyEncrypted` 存储的是加密后的密文，不是原文
- `LlmRole.code` 作为主键，使用语义化编码便于代码中引用
- `LlmRole.provider` 可为 null，表示该角色暂未绑定任何厂商（界面显示"未配置"）
- `options` JSON 字段存储模型参数，合并策略：`array_merge(provider.options, role.options)`

#### 1.2 Migration

```bash
bin/console make:migration
# 生成的 migration 需包含：
# CREATE TABLE platform_llm_provider
# CREATE TABLE platform_llm_role
# INSERT INTO platform_llm_role 预置默认角色数据
```

**Seeder / 默认角色数据：**

```sql
INSERT INTO platform_llm_role (code, label, is_enabled) VALUES
('view_editor',       '视图设计器',   true),
('entity_generation', '实体生成',     true),
('workflow_analysis', '工作流分析',   false),
('query_assistant',   '查询助手',     false),
('chat',              '通用对话',     false),
('coding',            '代码生成',     false);
```

#### 1.3 API Key 加密服务

**`src/Service/Platform/LlmEncryptor.php`**

```php
class LlmEncryptor
{
    private string $cipher = 'aes-256-gcm';
    private string $key;

    public function __construct(
        #[Autowire('%env(LLM_ENCRYPTION_KEY)%')] string $key
    ) {
        $this->key = base64_decode($key);
    }

    public function encrypt(string $plaintext): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext, $this->cipher, $this->key,
            OPENSSL_RAW_DATA, $iv, $tag, '', 16
        );
        return base64_encode($iv . $tag . $ciphertext);
    }

    public function decrypt(string $encoded): ?string
    {
        $data = base64_decode($encoded, true);
        if ($data === false || strlen($data) < 28) return null;
        $iv = substr($data, 0, 12);
        $tag = substr($data, 12, 16);
        $ciphertext = substr($data, 28);
        $result = openssl_decrypt($ciphertext, $this->cipher, $this->key,
            OPENSSL_RAW_DATA, $iv, $tag);
        return $result === false ? null : $result;
    }
}
```

**需要生成并配置密钥：**

```bash
php -r "echo base64_encode(random_bytes(32));"
```

写入 `.env.local`:

```env
LLM_ENCRYPTION_KEY=生成的base64密钥
```

#### 1.4 Controller

**`src/Controller/Admin/Platform/LlmConfigController.php`**

```php
#[Route('/admin/system/llm-config')]
#[IsGranted('ROLE_ADMIN')]
class LlmConfigController extends AbstractController
{
    #[Route('', name: 'admin_llm_config_index')]
    public function index(LlmProviderRepository $providerRepo, LlmRoleRepository $roleRepo): Response
    {
        return $this->render('admin/platform/llm_config/index.html.twig', [
            'providers' => $providerRepo->findBy([], ['sortOrder' => 'ASC']),
            'roles'     => $roleRepo->findBy([], ['code' => 'ASC']),
        ]);
    }

    #[Route('/provider/create', name: 'admin_llm_provider_create')]
    public function createProvider(Request $request): Response { /* ... */ }

    #[Route('/provider/{id}/edit', name: 'admin_llm_provider_edit')]
    public function editProvider(Request $request, LlmProvider $provider): Response { /* ... */ }

    #[Route('/provider/{id}/delete', name: 'admin_llm_provider_delete')]
    public function deleteProvider(LlmProvider $provider): Response { /* ... */ }

    #[Route('/role/{code}/edit', name: 'admin_llm_role_edit')]
    public function editRole(Request $request, LlmRole $role): Response { /* ... */ }
}
```

#### 1.5 Form Types

**`src/Form/Platform/LlmProviderType.php`**

```php
class LlmProviderType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => '名称'])
            ->add('provider', ChoiceType::class, [
                'label' => '厂商',
                'choices' => [
                    'OpenAI'    => 'openai',
                    'Anthropic' => 'anthropic',
                    'Azure'     => 'azure',
                    'Ollama'    => 'ollama',
                    '自定义'     => 'custom',
                ],
            ])
            ->add('model', TextType::class, ['label' => '模型名称'])
            ->add('apiKey', TextType::class, [
                'label' => 'API Key',
                'required' => $options['is_new'],
                'help' => $options['is_new'] ? '' : '留空表示不修改',
            ])
            ->add('apiEndpoint', UrlType::class, [
                'label' => '接口地址（可选）',
                'required' => false,
            ])
            ->add('temperature', NumberType::class, [
                'label' => 'Temperature',
                'scale' => 1,
                'required' => false,
            ])
            ->add('maxTokens', IntegerType::class, [
                'label' => 'Max Tokens',
                'required' => false,
            ]);
    }
}
```

**`src/Form/Platform/LlmRoleType.php`**

```php
class LlmRoleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('provider', EntityType::class, [
                'class' => LlmProvider::class,
                'label' => '绑定厂商',
                'placeholder' => '— 未配置 —',
                'required' => false,
            ])
            ->add('systemPrompt', TextareaType::class, [
                'label' => 'System Prompt',
                'required' => false,
                'attr' => ['rows' => 8],
            ])
            ->add('isEnabled', CheckboxType::class, [
                'label' => '启用',
                'required' => false,
            ]);
    }
}
```

#### 1.6 Twig 模板结构

```
templates/admin/platform/llm_config/
├── index.html.twig           # 列表页（卡片式布局）
├── _provider_card.html.twig  # 厂商卡片（可复用）
├── _role_badge.html.twig     # 角色用途标签
├── provider_form.html.twig   # 厂商编辑表单
└── role_form.html.twig       # 角色编辑表单
```

**`index.html.twig` 核心结构：**

```twig
{% extends 'admin/view_editor_layout.html.twig' %}

{% block app_content_container %}
<div class="llm-config-page">
  <div class="page-header">
    <h1>LLM 配置</h1>
    <a href="{{ path('admin_llm_provider_create') }}" class="btn primary">
      <i class="fa-solid fa-plus"></i> 新增厂商
    </a>
  </div>

  <!-- 厂商卡片列表 -->
  <div class="llm-provider-list">
    {% for provider in providers %}
      {% include 'admin/platform/llm_config/_provider_card.html.twig' %}
    {% endfor %}
  </div>

  <!-- 角色配置表 -->
  <div class="llm-role-section">
    <h2>角色绑定</h2>
    <table class="llm-role-table">
      {% for role in roles %}
        <tr>
          <td class="role-code">{{ role.code }}</td>
          <td class="role-label">{{ role.label }}</td>
          <td class="role-provider">
            {% if role.provider %}
              <span class="badge badge-success">{{ role.provider.name }}</span>
            {% else %}
              <span class="badge badge-muted">未配置</span>
            {% endif %}
          </td>
          <td class="role-actions">
            <a href="{{ path('admin_llm_role_edit', {code: role.code}) }}">
              <i class="fa-solid fa-pen"></i>
            </a>
          </td>
        </tr>
      {% endfor %}
    </table>
  </div>
</div>
{% endblock %}
```

#### 1.7 CSS 样式

追加到 `public/sunui/admin/platform/llm_config.css`：

```css
.llm-config-page { padding: 24px; max-width: 960px; margin: 0 auto; }

.llm-provider-list {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 16px;
  margin: 24px 0;
}

.llm-provider-card {
  border: 1px solid #e8e8e8;
  border-radius: 12px;
  padding: 20px;
  background: #fff;
  box-shadow: 0 2px 8px rgba(0,0,0,.06);
  transition: box-shadow .2s;
}
.llm-provider-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.1); }

.llm-provider-card .card-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 12px;
}

.llm-provider-card .provider-name {
  font-size: 16px;
  font-weight: 600;
}

.llm-provider-card .provider-model {
  color: #666;
  font-size: 13px;
  font-family: monospace;
}

.llm-provider-card .role-badges {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 12px;
}

.llm-provider-card .role-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 2px 10px;
  border-radius: 12px;
  font-size: 12px;
  background: #e6f4ff;
  color: #1677ff;
}

.llm-provider-card .card-actions {
  display: flex;
  gap: 8px;
  margin-top: 16px;
  padding-top: 12px;
  border-top: 1px solid #f0f0f0;
}
```

#### 1.8 加密存储与回显细节

在 Form 层处理加密/解密：

```php
// LlmProviderType 的 buildForm 中
$builder->add('apiKey', TextType::class, [
    'mapped' => false,   // 不直接映射到实体字段
    'required' => $options['is_new'],
    'help' => $options['is_new'] ? '' : '留空表示不修改',
]);

// Controller 中保存时：
if ($apiKey = $form->get('apiKey')->getData()) {
    $provider->setApiKeyEncrypted($encryptor->encrypt($apiKey));
}

// 编辑时设置 placeholder 掩码：
if ($provider->getApiKeyEncrypted()) {
    $form->get('apiKey')->setData('••••••••' . substr($decrypted, -4));
}
```

#### 1.9 测试连接功能

**Controller 端点：**

```php
#[Route('/provider/{id}/test', name: 'admin_llm_provider_test', methods: ['POST'])]
public function testProvider(LlmProvider $provider, LlmEncryptor $encryptor): JsonResponse
{
    $gateway = LlmGatewayFactory::create($provider, $encryptor);
    $start = microtime(true);
    try {
        $result = $gateway->chat([
            ['role' => 'user', 'content' => '回复"OK"即可，不要多余文字。']
        ]);
        $elapsed = round((microtime(true) - $start) * 1000);
        return $this->json([
            'success' => true,
            'elapsed' => $elapsed,
            'reply'   => trim($result['content'] ?? ''),
        ]);
    } catch (\Exception $e) {
        return $this->json([
            'success' => false,
            'error'   => $e->getMessage(),
        ], 422);
    }
}
```

**前端 AJAX：**

```javascript
// llm_config.js
$('.btn-test-connection').on('click', function() {
    const $btn = $(this);
    const providerId = $btn.data('provider-id');
    const $status = $btn.closest('.llm-provider-card').find('.test-status');

    $btn.prop('disabled', true).text('测试中...');
    $status.html('<i class="fa-solid fa-spinner fa-spin"></i>');

    $.post(`/admin/system/llm-config/provider/${providerId}/test`)
        .done(function(res) {
            $status.html(`
                <span class="text-success">
                    <i class="fa-solid fa-check-circle"></i>
                    ${res.elapsed}ms
                </span>
            `);
        })
        .fail(function(xhr) {
            const err = xhr.responseJSON?.error || '连接失败';
            $status.html(`<span class="text-danger"><i class="fa-solid fa-times-circle"></i> ${err}</span>`);
        })
        .always(function() {
            $btn.prop('disabled', false).text('测试连接');
        });
});
```

#### 1.10 验证清单

- [ ] Provider CRUD 完整（创建 → 编辑 → 删除）
- [ ] API Key 加密后写入 DB，不可明文查询
- [ ] 编辑时 API Key 显示掩码，留空不修改
- [ ] 测试连接成功返回耗时和回复
- [ ] 测试连接失败显示具体错误
- [ ] 角色绑定 / 解绑生效
- [ ] 开关启用/停用生效
- [ ] 所有操作有 Flash 消息反馈

---

### 第二阶段：Gateway 实现（预估 3-4 天）

#### 2.1 接口定义

**`src/Service/Platform/Llm/LlmGatewayInterface.php`**

```php
namespace App\Service\Platform\Llm;

interface LlmGatewayInterface
{
    /** 标准 Chat Completion */
    public function chat(array $messages, array $options = []): array;

    /** Stream Chat Completion（用于 SSE） */
    public function chatStream(array $messages, array $options = []): \Generator;

    /** 测试连接 */
    public function testConnection(): array;

    /** 获取厂商名 */
    public function getProviderName(): string;

    /** 获取当前模型名 */
    public function getModelName(): string;
}
```

#### 2.2 统一 DTO

**`src/Service/Platform/Llm/Message.php`**

```php
class Message
{
    public function __construct(
        public readonly string $role,    // system | user | assistant | tool
        public readonly string $content,
        public readonly ?array $toolCalls = null,
        public readonly ?string $toolCallId = null,
    ) {}
}
```

**`src/Service/Platform/Llm/ChatResponse.php`**

```php
class ChatResponse
{
    public function __construct(
        public readonly string $content,
        public readonly string $finishReason,  // stop | length | tool_calls
        public readonly int    $inputTokens,
        public readonly int    $outputTokens,
        public readonly float  $elapsedMs,
        public readonly ?array $toolCalls = null,
    ) {}
}
```

#### 2.3 OpenAI 适配器（参考实现）

**`src/Service/Platform/Llm/Gateway/OpenAiGateway.php`**

```php
class OpenAiGateway implements LlmGatewayInterface
{
    private string $apiKey;
    private string $model;
    private string $endpoint;
    private array  $defaultOptions;

    public function __construct(LlmProvider $provider, LlmEncryptor $encryptor)
    {
        $this->apiKey   = $encryptor->decrypt($provider->getApiKeyEncrypted());
        $this->model    = $provider->getModel();
        $this->endpoint = $provider->getApiEndpoint()
            ?: 'https://api.openai.com/v1';
        $this->defaultOptions = $provider->getOptions() ?? [];
    }

    public function chat(array $messages, array $options = []): ChatResponse
    {
        $start = microtime(true);
        $opts  = array_merge($this->defaultOptions, $options);

        $payload = [
            'model'    => $this->model,
            'messages' => $messages,
            'temperature' => $opts['temperature'] ?? 0.7,
            'max_tokens'  => $opts['maxTokens'] ?? 4096,
        ];

        $ch = curl_init($this->endpoint . '/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) throw new LlmException("CURL error: $error");
        if ($httpCode !== 200) {
            throw new LlmException("HTTP $httpCode: " . $response);
        }

        $data  = json_decode($response, true);
        $elapsed = (microtime(true) - $start) * 1000;
        $choice  = $data['choices'][0] ?? [];

        return new ChatResponse(
            content:      $choice['message']['content'] ?? '',
            finishReason: $choice['finish_reason'] ?? 'stop',
            inputTokens:  $data['usage']['prompt_tokens'] ?? 0,
            outputTokens: $data['usage']['completion_tokens'] ?? 0,
            elapsedMs:    $elapsed,
            toolCalls:    $choice['message']['tool_calls'] ?? null,
        );
    }

    public function chatStream(array $messages, array $options = []): \Generator
    {
        // SSE 流式调用，逐行 yield
        // 实现略（与 chat 类似但使用 CURLOPT_WRITEFUNCTION）
    }

    public function testConnection(): array
    {
        $res = $this->chat([
            ['role' => 'user', 'content' => 'Hi']
        ]);
        return ['success' => true, 'reply' => $res->content];
    }

    public function getProviderName(): string { return 'openai'; }
    public function getModelName(): string { return $this->model; }
}
```

#### 2.4 Anthropic 适配器概要

```php
class AnthropicGateway implements LlmGatewayInterface
{
    // endpoint: https://api.anthropic.com/v1/messages
    // Header: x-api-key + anthropic-version: 2023-06-01
    // Payload 格式与 OpenAI 不同：
    //   { model, messages: [{role, content}], max_tokens, system }
    // System prompt 在顶层 system 字段，不在 messages 数组中
    // tool_choice 使用 tools / tool_choice 字段
}
```

#### 2.5 Ollama 适配器概要

```php
class OllamaGateway implements LlmGatewayInterface
{
    // endpoint: http://localhost:11434/api/chat
    // 无 API Key
    // Payload: { model, messages, stream: false, options: {temperature} }
    // Response: { message: {role, content}, done, total_duration }
}
```

#### 2.6 工厂 + 角色路由

**`src/Service/Platform/Llm/LlmGatewayFactory.php`**

```php
class LlmGatewayFactory
{
    public function __construct(
        private LlmEncryptor $encryptor,
    ) {}

    public function create(LlmProvider $provider): LlmGatewayInterface
    {
        return match ($provider->getProvider()) {
            'openai'    => new OpenAiGateway($provider, $this->encryptor),
            'anthropic' => new AnthropicGateway($provider, $this->encryptor),
            'ollama'    => new OllamaGateway($provider, $this->encryptor),
            'azure'     => new AzureGateway($provider, $this->encryptor),
            'custom'    => new CustomGateway($provider, $this->encryptor),
            default     => throw new \InvalidArgumentException(
                "Unsupported provider: {$provider->getProvider()}"
            ),
        };
    }
}
```

#### 2.7 LlmRouter（自动路由 + Failover）

**`src/Service/Platform/Llm/LlmRouter.php`**

```php
class LlmRouter
{
    public function __construct(
        private LlmRoleRepository   $roleRepo,
        private LlmGatewayFactory   $factory,
        private LoggerInterface     $logger,
    ) {}

    public function chatByRole(string $roleCode, array $messages, array $options = []): ChatResponse
    {
        $role = $this->roleRepo->find($roleCode);
        if (!$role || !$role->isEnabled() || !$role->getProvider()) {
            throw new LlmException("Role '$roleCode' is not configured or disabled");
        }

        // 合并角色级选项
        $mergedOptions = array_merge(
            $role->getOptions() ?? [],
            $options,
        );

        // 构建消息（前置 system prompt）
        $fullMessages = [];
        if ($role->getSystemPrompt()) {
            $fullMessages[] = ['role' => 'system', 'content' => $role->getSystemPrompt()];
        }
        foreach ($messages as $msg) {
            $fullMessages[] = $msg;
        }

        $provider = $role->getProvider();
        $gateway  = $this->factory->create($provider);
        $this->logger->info('LLM call', [
            'role'   => $roleCode,
            'model'  => $gateway->getModelName(),
            'tokens' => array_sum(array_map(fn($m) => strlen($m['content'] ?? ''), $messages)),
        ]);

        try {
            return $gateway->chat($fullMessages, $mergedOptions);
        } catch (\Exception $e) {
            $this->logger->error('LLM call failed', [
                'role'     => $roleCode,
                'provider' => $provider->getName(),
                'error'    => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /** 带 Failover 的调用 */
    public function chatByRoleWithFallback(string $roleCode, array $messages, array $options = []): ChatResponse
    {
        $role = $this->roleRepo->find($roleCode);
        if (!$role) throw new LlmException("Role '$roleCode' not found");

        $providers = [$role->getProvider()];
        // 如果有备用配置可追加... 当前简化版本仅用主配置

        $lastException = null;
        foreach ($providers as $provider) {
            if (!$provider || !$provider->isEnabled()) continue;
            try {
                return $this->chatByRole($roleCode, $messages, $options);
            } catch (LlmException $e) {
                $lastException = $e;
                continue;
            }
        }

        throw $lastException ?? new LlmException("All providers failed for role '$roleCode'");
    }
}
```

#### 2.8 统一异常

**`src/Service/Platform/Llm/LlmException.php`**

```php
class LlmException extends \RuntimeException
{
    public function __construct(
        string     $message = '',
        int        $code = 0,
        ?\Throwable $previous = null,
        public readonly ?array $context = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
```

#### 2.9 验证清单

- [ ] OpenAI Gateway 完整实现（chat + testConnection）
- [ ] Anthropic Gateway 完整实现
- [ ] Ollama Gateway 完整实现
- [ ] Gateway Factory 根据 provider 类型正确实例化
- [ ] LlmRouter.chatByRole 正确路由到绑定的 Provider
- [ ] System Prompt 正确前置到 messages
- [ ] 角色级 options 正确合并覆盖
- [ ] 异常时记录错误日志
- [ ] 单元测试：mock HTTP 请求测试 Gateway

---

### 第三阶段：业务集成（预估 3-5 天）

#### 3.1 AI 调用 API 端点

**`src/Controller/Api/Admin/Platform/LlmChatController.php`**

```php
#[Route('/api/admin/ai/chat', name: 'api_ai_chat', methods: ['POST'])]
public function chat(
    Request    $request,
    LlmRouter  $router,
    LoggerInterface $logger,
): JsonResponse {
    $payload  = $request->toArray();
    $roleCode = $payload['role'] ?? 'view_editor';
    $messages = $payload['messages'] ?? [];

    if (empty($messages)) {
        return $this->json(['error' => 'messages 不能为空'], 400);
    }

    try {
        $response = $router->chatByRole($roleCode, $messages);

        // 审计日志
        $logger->info('AI chat', [
            'user'    => $this->getUser()->getUserIdentifier(),
            'role'    => $roleCode,
            'input'   => $response->inputTokens,
            'output'  => $response->outputTokens,
            'elapsed' => round($response->elapsedMs),
        ]);

        return $this->json([
            'content'       => $response->content,
            'finishReason'  => $response->finishReason,
            'inputTokens'   => $response->inputTokens,
            'outputTokens'  => $response->outputTokens,
            'elapsedMs'     => $response->elapsedMs,
        ]);
    } catch (LlmException $e) {
        return $this->json([
            'error' => $e->getMessage(),
        ], 502);
    }
}
```

#### 3.2 Failover 增强

当主 Provider 失败时，自动尝试备用 Provider：

```yaml
# config/packages/llm.yaml
llm:
    failover:
        view_editor:
            - ~                    # 主配置（取 LlmRole 绑定的 Provider）
            - provider: ollama     # 备选 1
              model: qwen2.5-coder
            - provider: openai     # 备选 2
              model: gpt-4o-mini
```

或简化：直接在 `LlmRole` 实体中增加 `backupProviderId` 字段，配置界面提供下拉选择。

#### 3.3 审计日志

**`src/Entity/Platform/LlmAuditLog.php`**

```php
class LlmAuditLog
{
    #[ORM\Column(type: 'uuid')]
    private Uuid $userId;

    #[ORM\Column(length: 50)]
    private string $roleCode;

    #[ORM\Column(length: 100)]
    private string $model;

    #[ORM\Column(type: 'integer')]
    private int $inputTokens;

    #[ORM\Column(type: 'integer')]
    private int $outputTokens;

    #[ORM\Column(type: 'float')]
    private float $elapsedMs;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $success;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;
}
```

日志查询界面：系统设置 > LLM 配置 > 调用日志（简单列表，可按用户/角色/时间筛选）。

#### 3.4 视图设计器集成

参考 `ai-view.md`，在 AI 助手面板中调用：

```javascript
// editor_toolbar.js / ai_panel.js
async function aiChat(messages, role = 'view_editor') {
    const res = await fetch('/api/admin/ai/chat', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ role, messages }),
    });
    if (!res.ok) throw new Error((await res.json()).error);
    return await res.json();
}

// 使用示例
async function applyAIStyle(prompt) {
    const selectedHtml = getSelectedElement().outerHTML;
    const res = await aiChat([
        { role: 'system', content: '你是一个视图设计器 CSS 助手。' },
        { role: 'user', content: `当前元素: ${selectedHtml}\n\n需求: ${prompt}` },
    ]);
    const newHtml = parseToolCall(res.content);
    applyChanges(newHtml);
}
```

#### 3.5 限流（Rate Limiter）

```yaml
# config/packages/rate_limiter.yaml
framework:
    rate_limiter:
        ai_chat:
            policy: 'sliding_window'
            limit: 30
            interval: '1 minute'
            cache_pool: 'cache.app'
```

在 Controller 中应用：

```php
use Symfony\Component\RateLimiter\RateLimiterFactory;

public function chat(
    Request $request,
    LlmRouter $router,
    RateLimiterFactory $aiChatLimiter,
): JsonResponse {
    $limiter = $aiChatLimiter->create($this->getUser()->getUserIdentifier());
    if (!$limiter->consume()->isAccepted()) {
        return $this->json(['error' => '请求过于频繁，请稍后再试'], 429);
    }
    // ...
}
```

#### 3.6 可用模型列表（前端下拉辅助）

对于 OpenAI、Anthropic 等厂商，提供内置模型列表供选择：

**`src/Service/Platform/Llm/ModelRegistry.php`**

```php
class ModelRegistry
{
    private const MODELS = [
        'openai' => [
            'gpt-4o'              => 'GPT-4o',
            'gpt-4o-mini'         => 'GPT-4o Mini',
            'gpt-4-turbo'         => 'GPT-4 Turbo',
            'o1-mini'             => 'o1 Mini',
            'text-embedding-3-small' => 'Embedding v3 Small',
        ],
        'anthropic' => [
            'claude-3-5-sonnet-20241022' => 'Claude 3.5 Sonnet',
            'claude-3-haiku-20240307'    => 'Claude 3 Haiku',
        ],
        'ollama' => [
            'qwen2.5-coder'  => 'Qwen 2.5 Coder',
            'qwen2.5'        => 'Qwen 2.5',
            'llama3.2'       => 'Llama 3.2',
            'deepseek-coder' => 'DeepSeek Coder',
        ],
    ];

    public function getModels(string $provider): array
    {
        return self::MODELS[$provider] ?? [];
    }

    public function getAll(): array
    {
        return self::MODELS;
    }
}
```

在 Provider 编辑表单中，选择厂商后通过 AJAX 动态加载模型下拉选项：

```javascript
$('#llm_provider_provider').on('change', function() {
    const provider = $(this).val();
    $.getJSON(`/api/admin/llm/models/${provider}`, function(models) {
        const $select = $('#llm_provider_model');
        $select.empty();
        $.each(models, function(value, label) {
            $select.append(`<option value="${value}">${label}</option>`);
        });
    });
});
```

#### 3.7 验证清单

- [ ] `/api/admin/ai/chat` 端点正常工作
- [ ] 正确路由到角色绑定的 Provider
- [ ] System Prompt 正确注入
- [ ] Failover 在主 Provider 失败时降级到备用
- [ ] 审计日志记录每次调用
- [ ] 限流生效（30次/分钟）
- [ ] 视图设计器 AI 面板可调用后端接口
- [ ] 错误处理完善（超时、限流、无效 API Key 等）

---

### 第四阶段：测试与安全加固（预估 2 天）

#### 4.1 单元测试

```
tests/Service/Platform/Llm/
├── OpenAiGatewayTest.php
├── AnthropicGatewayTest.php
├── LlmRouterTest.php
├── LlmEncryptorTest.php
└── LlmGatewayFactoryTest.php
```

重点覆盖：
- Gateway 的 payload 构建是否正确
- LlmRouter 的 System Prompt 前置逻辑
- LlmEncryptor 加密/解密一致性
- Factory 的 provider 类型匹配

#### 4.2 集成测试

```
tests/Controller/Api/Admin/Platform/LlmChatControllerTest.php
tests/Controller/Admin/Platform/LlmConfigControllerTest.php
```

重点覆盖：
- API 端点鉴权
- 无效 role code 返回 400
- 无效 API Key 返回 502
- 表单 CRUD 流程

#### 4.3 安全加固

- [ ] 所有 LLM 相关路由限 `ROLE_ADMIN`
- [ ] API Key 密文在 DB 中不可被 SELECT 直接读取明文
- [ ] `.env` 中的 `LLM_ENCRYPTION_KEY` 使用 Symfony Secrets 存储（生产环境）
- [ ] AI 调用 API 端点上应用 CSRF token 或 API token 认证
- [ ] XSS 过滤：AI 返回的 HTML 内容在渲染到视图设计器前经过 DOMPurify
- [ ] 日志中屏蔽敏感内容（API Key、用户隐私数据）

---

### 注意事项

- API Key 一旦保存不可明文回显，编辑时显示 `••••••••` + 最后四位掩码
- 厂商模型列表可从 API 动态拉取，也提供内置列表作为备选（`ModelRegistry`）
- System Prompt 支持 Twig 变量注入（如 `{{ project_name }}`）
- 所有 LLM 调用超时设置为 60 秒，可在 Provider 级别配置
- 生产环境建议使用 Redis 缓存 Provider 配置（TTL: 3600s），配置变更时通过事件清除缓存

---

### 完整文件清单

```
新增文件：

src/Entity/Platform/LlmProvider.php
src/Entity/Platform/LlmRole.php
src/Entity/Platform/LlmAuditLog.php
src/Repository/Platform/LlmProviderRepository.php
src/Repository/Platform/LlmRoleRepository.php
src/Service/Platform/Llm/LlmGatewayInterface.php
src/Service/Platform/Llm/LlmException.php
src/Service/Platform/Llm/Message.php
src/Service/Platform/Llm/ChatResponse.php
src/Service/Platform/Llm/LlmGatewayFactory.php
src/Service/Platform/Llm/LlmRouter.php
src/Service/Platform/Llm/ModelRegistry.php
src/Service/Platform/Llm/Gateway/OpenAiGateway.php
src/Service/Platform/Llm/Gateway/AnthropicGateway.php
src/Service/Platform/Llm/Gateway/OllamaGateway.php
src/Service/Platform/Llm/Gateway/AzureGateway.php
src/Service/Platform/Llm/Gateway/CustomGateway.php
src/Service/Platform/LlmEncryptor.php
src/Controller/Admin/Platform/LlmConfigController.php
src/Controller/Api/Admin/Platform/LlmChatController.php
src/Form/Platform/LlmProviderType.php
src/Form/Platform/LlmRoleType.php
templates/admin/platform/llm_config/index.html.twig
templates/admin/platform/llm_config/_provider_card.html.twig
templates/admin/platform/llm_config/_role_badge.html.twig
templates/admin/platform/llm_config/provider_form.html.twig
templates/admin/platform/llm_config/role_form.html.twig
public/sunui/admin/platform/llm_config.css
public/sunui/admin/platform/llm_config.js
config/packages/llm.yaml
config/packages/rate_limiter.yaml
migrations/Version_llm_config.php

修改文件：

templates/admin/system/sidebar.html.twig    # 增加 LLM 配置菜单项
config/routes/admin.yaml                     # 增加路由

