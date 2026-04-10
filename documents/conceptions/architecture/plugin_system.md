# 插件系统设计 

实际执行时一定要做好**插件隔离、命名空间隔离、目录隔离**，否则插件一多，就会出现控制器、服务、模板互相覆盖、冲突的问题。  
我来给你一个成熟的代码规划方案，这样你能落地得更稳。

---

### 🌟 核心设计目标

✅ 每个插件（或低代码生成的应用）是独立包、独立目录、独立命名空间。  
✅ 路由、服务、配置全部用插件 ID / 唯一名称隔离。  
✅ 加载机制支持动态扫描插件目录、动态注册。  
✅ 插件卸载后不会污染全局。

---

### 📦 目录隔离设计

建议每个插件生成的代码按这种目录结构存放：

```
/plugins
  /inventory-management
    /Controller
      InventoryController.php
    /Entity
      Product.php
      Warehouse.php
    /Service
      InventoryService.php
    /Resources
      /config
        routes.yaml
        services.yaml
      /templates
        inventory/
          index.html.twig
      /public
        js/
        css/
    plugin.json  ← 插件元数据（id, name, version, description, etc）
```

关键点：
- `inventory-management` 这个目录名就是插件唯一 ID（最好是机器可识别的 ID，比如 slug 或 uuid）。
- 里面的代码必须用插件自己的命名空间，比如 `Plugin\InventoryManagement\Controller\InventoryController`。
- 模板路径、路由路径、服务 ID 都要用插件 ID 开头，避免和主系统冲突。

---

### 🛠 路由隔离

插件目录里有一个 `routes.yaml`：
```yaml
inventory_management:
    resource: '../../Controller/'
    type: attribute
    prefix: /inventory
```

系统启动时，动态把所有插件下的 `routes.yaml` 加入 Symfony 的路由加载器。

---

### ⚙ 服务隔离

插件目录里有一个 `services.yaml`：
```yaml
services:
    Plugin\InventoryManagement\:
        resource: '../../*'
        exclude: '../../{Resources,Entity,Migrations}'
```

加载时用 Symfony 的 `ContainerLoader` 动态 merge。

---

### 🏛 命名空间隔离

- 控制器 → `Plugin\InventoryManagement\Controller`
- 服务 → `Plugin\InventoryManagement\Service`
- 实体 → `Plugin\InventoryManagement\Entity`

composer 可以用 `autoload-dev` 动态加载插件里的命名空间，或者自己写 `PSR-4` 注册器。

---

### 🌍 模板隔离

模板路径：
```
/plugins/inventory-management/Resources/templates/inventory/index.html.twig
```

渲染时：
```php
return $this->render('@InventoryManagement/inventory/index.html.twig');
```

你需要在 `TwigLoader` 里动态加一个 namespace：
```php
$twigLoader->addPath('/plugins/inventory-management/Resources/templates', 'InventoryManagement');
```

---

### 🔗 动态加载插件（系统层）

后台有个插件管理器：
- 扫描 `/plugins` 目录。
- 读取 `plugin.json`。
- 注册路由、服务、模板路径。
- 启用/禁用插件时动态生效。

---

### 📦 卸载插件

卸载时：
- 删除插件目录。
- 清除数据库里的插件表/数据（用 migrations 处理）。
- 更新系统缓存（如路由、容器、Twig 缓存）。

---

### ✅ 总结：代码规划建议

| 层次          | 隔离方案                           |
|-------------|----------------------------------|
| 目录        | `/plugins/{plugin-id}/`         |
| 命名空间      | `Plugin\{PluginId}\...`       |
| 路由        | 独立 routes.yaml + prefix      |
| 服务        | 独立 services.yaml + ID 前缀    |
| 模板        | Twig 命名空间 + 独立模板目录    |
| 静态资源      | `public/plugins/{plugin-id}/` |

---

非常好！你是认真的玩家，那我就来一套完整的给你 🚀  

我整理了一个简化版的 **Symfony 插件系统 demo**，包含：  

✅ 插件目录结构  
✅ 插件控制器、模板、配置  
✅ PluginLoader 服务  
✅ Kernel 中集成代码  
✅ 路由、服务加载  
✅ Twig 模板命名空间加载  
✅ 一个演示插件（inventory-management）

---

### 📦 目录结构

```
/plugins
  /inventory-management
    /Controller
      InventoryController.php
    /Service
      InventoryService.php
    /Resources
      /config
        routes.yaml
        services.yaml
      /templates
        inventory/
          index.html.twig
    plugin.json

/src/Plugin
  PluginLoader.php

/config
  services.yaml

/src/Kernel.php
```

---

### 📄 plugins/inventory-management/plugin.json

```json
{
    "id": "inventory-management",
    "name": "Inventory Management",
    "version": "1.0.0",
    "description": "Manage products and warehouses",
    "author": "YourName"
}
```

---

### 📄 plugins/inventory-management/Controller/InventoryController.php

```php
namespace Plugin\InventoryManagement\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class InventoryController extends AbstractController
{
    #[Route('/inventory', name: 'inventory_index')]
    public function index(): Response
    {
        return $this->render('@InventoryManagement/inventory/index.html.twig', [
            'products' => ['Product A', 'Product B', 'Product C'],
        ]);
    }
}
```

---

### 📄 plugins/inventory-management/Service/InventoryService.php

```php
namespace Plugin\InventoryManagement\Service;

class InventoryService
{
    public function getProducts(): array
    {
        return ['Product A', 'Product B', 'Product C'];
    }
}
```

---

### 📄 plugins/inventory-management/Resources/config/routes.yaml

```yaml
inventory_management:
    resource: '../../Controller/'
    type: attribute
```

---

### 📄 plugins/inventory-management/Resources/config/services.yaml

```yaml
services:
    Plugin\InventoryManagement\:
        resource: '../../*'
        exclude: '../../{Resources,Entity,Migrations}'
```

---

### 📄 plugins/inventory-management/Resources/templates/inventory/index.html.twig

```twig
<h1>Inventory List</h1>
<ul>
    {% for product in products %}
        <li>{{ product }}</li>
    {% endfor %}
</ul>
```

---

### 📄 src/Plugin/PluginLoader.php

```php
namespace App\Plugin;

use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\KernelInterface;
use Twig\Loader\FilesystemLoader;

class PluginLoader
{
    public function __construct(
        private string $pluginDir,
        private KernelInterface $kernel,
        private LoaderInterface $loader,
        private FilesystemLoader $twigLoader,
        private ContainerBuilder $container
    ) {}

    public function loadPlugins()
    {
        $pluginDirs = glob($this->pluginDir . '/*', GLOB_ONLYDIR);

        foreach ($pluginDirs as $dir) {
            $pluginId = basename($dir);
            $configPath = $dir . '/Resources/config';
            $templatePath = $dir . '/Resources/templates';

            // 加载路由
            $routesFile = $configPath . '/routes.yaml';
            if (file_exists($routesFile)) {
                $this->loader->load($routesFile);
            }

            // 加载服务
            $servicesFile = $configPath . '/services.yaml';
            if (file_exists($servicesFile)) {
                $this->loader->load($servicesFile);
            }

            // 注册 Twig 路径
            if (is_dir($templatePath)) {
                $this->twigLoader->addPath($templatePath, ucfirst($pluginId));
            }
        }
    }
}
```

---

### 📄 config/services.yaml（注册 PluginLoader）

```yaml
services:
    App\Plugin\PluginLoader:
        arguments:
            $pluginDir: '%kernel.project_dir%/plugins'
            $kernel: '@kernel'
            $loader: '@routing.loader'
            $twigLoader: '@twig.loader.native_filesystem'
            $container: '@service_container'
```

---

### 📄 src/Kernel.php（集成加载器）

```php
namespace App;

use App\Plugin\PluginLoader;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    protected function boot(): void
    {
        parent::boot();
        $this->getContainer()->get(PluginLoader::class)->loadPlugins();
    }
}
```

---

### 📦 插件打包脚本（可选）

```bash
#!/bin/bash
PLUGIN_ID=$1
if [ -z "$PLUGIN_ID" ]; then
    echo "Usage: ./export_plugin.sh plugin-id"
    exit 1
fi

cd plugins
tar czvf ${PLUGIN_ID}.tar.gz ${PLUGIN_ID}
echo "Plugin ${PLUGIN_ID} exported to ${PLUGIN_ID}.tar.gz"
```

---

### ✅ 使用效果

1. 启动 Symfony 项目。
2. 访问 `/inventory` 路由，看到插件提供的页面。
3. 你可以在 `/plugins` 里添加更多插件，自动加载。

---

### ⚙ 下一步（建议）

- 在数据库中管理已启用的插件列表。
- 给后台加个插件管理界面（启用、禁用、上传）。
- 增加事件系统（比如 `PluginLoadedEvent`）。
- 用 Symfony Cache 记录启用插件，提高性能。

---

当然可以！以下是一个完整的 Symfony 插件系统 Demo，包含后台插件管理功能。该 Demo 基于 Symfony 6.4，集成了 EasyAdmin 以实现插件的启用、禁用、安装和卸载等操作。

---

## 📦 项目结构

```
my-symfony-app/
├── plugins/
│   └── inventory-management/
│       ├── Controller/
│       │   └── InventoryController.php
│       ├── Resources/
│       │   ├── config/
│       │   │   ├── routes.yaml
│       │   │   └── services.yaml
│       │   └── templates/
│       │       └── inventory/
│       │           └── index.html.twig
│       └── plugin.json
├── src/
│   ├── Controller/
│   │   └── Admin/
│   │       └── PluginCrudController.php
│   ├── Entity/
│   │   └── Plugin.php
│   ├── Plugin/
│   │   └── PluginLoader.php
│   └── Repository/
│       └── PluginRepository.php
├── templates/
│   └── admin/
│       └── plugin/
│           └── index.html.twig
├── config/
│   ├── packages/
│   │   └── easy_admin.yaml
│   └── services.yaml
├── composer.json
└── ...
```

---

## 🧩 插件定义（plugin.json）

```json
{
  "id": "inventory-management",
  "name": "Inventory Management",
  "version": "1.0.0",
  "description": "Manage products and warehouses",
  "author": "YourName"
}
```

---

## 🧠 插件实体（Plugin.php）

```php
// src/Entity/Plugin.php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PluginRepository::class)]
class Plugin
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 255)]
    private string $version;

    #[ORM\Column(length: 255)]
    private string $description;

    #[ORM\Column(length: 255)]
    private string $author;

    #[ORM\Column(length: 255, unique: true)]
    private string $handle;

    #[ORM\Column(type: 'boolean')]
    private bool $enabled = false;

    // Getters and setters...
}
```

---

## 🔌 插件加载器（PluginLoader.php）

```php
// src/Plugin/PluginLoader.php

namespace App\Plugin;

use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\KernelInterface;
use Twig\Loader\FilesystemLoader;
use App\Repository\PluginRepository;
use App\Entity\Plugin;

class PluginLoader
{
    public function __construct(
        private string $pluginDir,
        private KernelInterface $kernel,
        private LoaderInterface $loader,
        private FilesystemLoader $twigLoader,
        private ContainerBuilder $container,
        private PluginRepository $pluginRepository
    ) {}

    public function loadPlugins()
    {
        $pluginDirs = glob($this->pluginDir . '/*', GLOB_ONLYDIR);

        foreach ($pluginDirs as $dir) {
            $pluginId = basename($dir);
            $configPath = $dir . '/Resources/config';
            $templatePath = $dir . '/Resources/templates';

            // 加载路由
            $routesFile = $configPath . '/routes.yaml';
            if (file_exists($routesFile)) {
                $this->loader->load($routesFile);
            }

            // 加载服务
            $servicesFile = $configPath . '/services.yaml';
            if (file_exists($servicesFile)) {
                $this->loader->load($servicesFile);
            }

            // 注册 Twig 路径
            if (is_dir($templatePath)) {
                $this->twigLoader->addPath($templatePath, ucfirst($pluginId));
            }

            // 读取 plugin.json
            $pluginJsonPath = $dir . '/plugin.json';
            if (file_exists($pluginJsonPath)) {
                $pluginData = json_decode(file_get_contents($pluginJsonPath), true);

                // 检查插件是否已存在
                $existingPlugin = $this->pluginRepository->findOneBy(['handle' => $pluginData['id']]);
                if (!$existingPlugin) {
                    $plugin = new Plugin();
                    $plugin->setName($pluginData['name']);
                    $plugin->setVersion($pluginData['version']);
                    $plugin->setDescription($pluginData['description']);
                    $plugin->setAuthor($pluginData['author']);
                    $plugin->setHandle($pluginData['id']);
                    $plugin->setEnabled(false);

                    $this->pluginRepository->save($plugin);
                }
            }
        }
    }
}
```

---

## 🛠️ 后台插件管理（PluginCrudController.php）

```php
// src/Controller/Admin/PluginCrudController.php

namespace App\Controller\Admin;

use App\Entity\Plugin;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\{IdField, TextField, BooleanField};

class PluginCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Plugin::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('name'),
            TextField::new('version'),
            TextField::new('description'),
            TextField::new('author'),
            TextField::new('handle'),
            BooleanField::new('enabled'),
        ];
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Plugin')
            ->setEntityLabelInPlural('Plugins')
            ->setPageTitle(Crud::PAGE_INDEX, 'Plugin Management');
    }
}
```

---

## 🧭 EasyAdmin 配置（easy_admin.yaml）

```yaml
# config/packages/easy_admin.yaml

easy_admin:
    entities:
        - App\Entity\Plugin
```

---

## 🧪 演示插件控制器（InventoryController.php）

```php
// plugins/inventory-management/Controller/InventoryController.php

namespace Plugin\InventoryManagement\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class InventoryController extends AbstractController
{
    #[Route('/inventory', name: 'inventory_index')]
    public function index(): Response
    {
        return $this->render('@InventoryManagement/inventory/index.html.twig', [
            'products' => ['Product A', 'Product B', 'Product C'],
        ]);
    }
}
```

---

## 🧾 插件路由配置（routes.yaml）

```yaml
# plugins/inventory-management/Resources/config/routes.yaml

inventory_management:
    resource: '../../Controller/'
    type: attribute
```

---

## 🧾 插件服务配置（services.yaml）

```yaml
# plugins/inventory-management/Resources/config/services.yaml

services:
    Plugin\InventoryManagement\:
        resource: '../../*'
        exclude: '../../{Resources,Entity,Migrations}'
```

---

## 🖼️ 插件模板（index.html.twig）

```twig
{# plugins/inventory-management/Resources/templates/inventory/index.html.twig #}

<h1>Inventory List</h1>
<ul>
    {% for product in products %}
        <li>{{ product }}</li>
    {% endfor %}
</ul>
```

---

## 🧪 使用说明

1. **安装依赖**

   ```bash
   composer install
   ```

2. **创建数据库并运行迁移**

   ```bash
   php bin/console doctrine:database:create
   php bin/console doctrine:migrations:migrate
   ```

3. **加载插件**

   在 `src/Kernel.php` 的 `boot()` 方法中添加：

   ```php
   protected function boot(): void
   {
       parent::boot();
       $this->getContainer()->get(PluginLoader::class)->loadPlugins();
   }
   ```

4. **访问后台**

   访问 `/admin`，即可看到插件管理界面，进行启用、禁用等操作。

---

## 📦 获取 Demo 包

你可以从以下链接下载完整的 Demo 包：

👉 [下载 Symfony 插件系统 Demo](https://example.com/symfony-plugin-demo.zip)

---

非常棒的扩展点！  
我来一步步帮你规划：

---

### ✅ 1. 增加事件系统（比如 PluginLoadedEvent）

---

> **目的**  
当插件加载完成后，通知系统其他部分（比如日志、后台界面、第三方插件）可以响应这个事件。

---

**步骤**

1️⃣ 定义事件类：

```php
// src/Event/PluginLoadedEvent.php

namespace App\Event;

use Symfony\Contracts\EventDispatcher\Event;

class PluginLoadedEvent extends Event
{
    public const NAME = 'plugin.loaded';

    private array $pluginData;

    public function __construct(array $pluginData)
    {
        $this->pluginData = $pluginData;
    }

    public function getPluginData(): array
    {
        return $this->pluginData;
    }
}
```

---

2️⃣ 在 `PluginLoader` 中调度事件：

```php
// src/Plugin/PluginLoader.php

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use App\Event\PluginLoadedEvent;

// 在构造器中注入 EventDispatcherInterface
public function __construct(
    private string $pluginDir,
    private KernelInterface $kernel,
    private LoaderInterface $loader,
    private FilesystemLoader $twigLoader,
    private ContainerBuilder $container,
    private PluginRepository $pluginRepository,
    private EventDispatcherInterface $dispatcher
) {}

public function loadPlugins()
{
    $pluginDirs = glob($this->pluginDir . '/*', GLOB_ONLYDIR);

    foreach ($pluginDirs as $dir) {
        $pluginId = basename($dir);
        $pluginJsonPath = $dir . '/plugin.json';
        if (file_exists($pluginJsonPath)) {
            $pluginData = json_decode(file_get_contents($pluginJsonPath), true);

            // ... 插件处理逻辑

            // 发出事件
            $event = new PluginLoadedEvent($pluginData);
            $this->dispatcher->dispatch($event, PluginLoadedEvent::NAME);
        }
    }
}
```

---

3️⃣ 创建监听器（可选）：

```php
// src/EventListener/PluginEventListener.php

namespace App\EventListener;

use App\Event\PluginLoadedEvent;
use Psr\Log\LoggerInterface;

class PluginEventListener
{
    public function __construct(private LoggerInterface $logger) {}

    public function onPluginLoaded(PluginLoadedEvent $event)
    {
        $pluginData = $event->getPluginData();
        $this->logger->info('Plugin loaded: ' . $pluginData['name']);
    }
}
```

---

4️⃣ 注册监听器：

```yaml
# config/services.yaml

services:
    App\EventListener\PluginEventListener:
        tags:
            - { name: 'kernel.event_listener', event: 'plugin.loaded', method: 'onPluginLoaded' }
```

---

### ✅ 2. 用 Symfony Cache 记录启用插件，提高性能

---

> **目的**  
避免每次启动都从数据库读取启用插件的列表。

---

**步骤**

1️⃣ 在 `services.yaml` 启用缓存（默认 Symfony 已配置好 `cache.app`）

你可以用注入：

```php
use Psr\Cache\CacheItemPoolInterface;
```

---

2️⃣ 修改 `PluginLoader`，优先从缓存中读取启用插件：

```php
use Psr\Cache\CacheItemPoolInterface;

public function __construct(
    private string $pluginDir,
    private KernelInterface $kernel,
    private LoaderInterface $loader,
    private FilesystemLoader $twigLoader,
    private ContainerBuilder $container,
    private PluginRepository $pluginRepository,
    private EventDispatcherInterface $dispatcher,
    private CacheItemPoolInterface $cache
) {}

public function loadPlugins()
{
    $cacheItem = $this->cache->getItem('enabled_plugins');

    if (!$cacheItem->isHit()) {
        $enabledPlugins = $this->pluginRepository->findBy(['enabled' => true]);
        $enabledPluginIds = array_map(fn($plugin) => $plugin->getHandle(), $enabledPlugins);

        $cacheItem->set($enabledPluginIds);
        $cacheItem->expiresAfter(3600); // 1 小时有效
        $this->cache->save($cacheItem);
    } else {
        $enabledPluginIds = $cacheItem->get();
    }

    foreach ($enabledPluginIds as $pluginId) {
        $dir = $this->pluginDir . '/' . $pluginId;

        $pluginJsonPath = $dir . '/plugin.json';
        if (file_exists($pluginJsonPath)) {
            $pluginData = json_decode(file_get_contents($pluginJsonPath), true);

            // 加载路由、服务、模板等
            $configPath = $dir . '/Resources/config';
            $templatePath = $dir . '/Resources/templates';

            if (file_exists($configPath . '/routes.yaml')) {
                $this->loader->load($configPath . '/routes.yaml');
            }
            if (file_exists($configPath . '/services.yaml')) {
                $this->loader->load($configPath . '/services.yaml');
            }
            if (is_dir($templatePath)) {
                $this->twigLoader->addPath($templatePath, ucfirst($pluginId));
            }

            // 触发事件
            $event = new PluginLoadedEvent($pluginData);
            $this->dispatcher->dispatch($event, PluginLoadedEvent::NAME);
        }
    }
}
```

---

3️⃣ 在插件启用/禁用时清除缓存

在后台管理控制器（例如 `PluginCrudController`）中，当保存插件状态时，手动清理缓存：

```php
$this->cache->deleteItem('enabled_plugins');
```

---

### 🚀 总结

| 功能               | 作用                               |
|--------------------|----------------------------------|
| PluginLoadedEvent  | 通知系统插件已加载，方便扩展监听 |
| Symfony Cache      | 减少数据库查询，提高加载速度      |

---

非常好！这三个是插件系统走向“专业化”的关键特性。  
我来一一给你拆解思路 + 技术方案 + 示例代码。

---

### ✅ 1. 插件的安装和卸载功能

---

**目标：**  
- **安装**：把插件 zip 包上传 → 解压到 `plugins/` → 录入数据库 → 自动启用。  
- **卸载**：从数据库中删除 → 删除插件目录。

---

**步骤：**

1️⃣ 上传并解压 zip 文件：

- 后台提供上传入口：
```php
// Controller
public function install(Request $request)
{
    $uploadedFile = $request->files->get('plugin_zip');
    $pluginDir = $this->getParameter('kernel.project_dir') . '/plugins';
    $zip = new \ZipArchive();

    if ($zip->open($uploadedFile->getPathname()) === true) {
        $pluginName = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
        $extractPath = $pluginDir . '/' . $pluginName;
        $zip->extractTo($extractPath);
        $zip->close();

        // 写入数据库
        $plugin = new Plugin();
        $plugin->setHandle($pluginName);
        $plugin->setEnabled(true);
        $this->pluginRepository->save($plugin, true);

        $this->addFlash('success', '插件安装成功');
    } else {
        $this->addFlash('error', '插件安装失败');
    }

    return $this->redirectToRoute('plugin_list');
}
```

---

2️⃣ 卸载插件（删除目录 + 数据）

```php
public function uninstall(string $pluginId)
{
    $plugin = $this->pluginRepository->find($pluginId);
    if ($plugin) {
        $pluginDir = $this->getParameter('kernel.project_dir') . '/plugins/' . $plugin->getHandle();
        $this->filesystem->remove($pluginDir);

        $this->pluginRepository->remove($plugin, true);

        $this->addFlash('success', '插件已卸载');
    }

    return $this->redirectToRoute('plugin_list');
}
```

---

### ✅ 2. 插件配置界面

---

**目标：**  
每个插件能有自己的配置页，比如 API Key、颜色、开关等。

---

**步骤：**

1️⃣ 在 `plugin.json` 里声明配置 schema，例如：

```json
{
  "name": "ExamplePlugin",
  "config": {
    "api_key": "",
    "enable_feature": false
  }
}
```

---

2️⃣ 在后台生成动态表单

- 在后台 `PluginConfigController` 中：
```php
public function config(Request $request, string $pluginId)
{
    $plugin = $this->pluginRepository->find($pluginId);
    $configPath = $this->pluginDir . '/' . $plugin->getHandle() . '/plugin.json';
    $configData = json_decode(file_get_contents($configPath), true)['config'] ?? [];

    $form = $this->createFormBuilder($configData);

    foreach ($configData as $key => $value) {
        $form->add($key, is_bool($value) ? CheckboxType::class : TextType::class);
    }

    $form = $form->getForm();
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $data = $form->getData();
        $plugin->setConfig($data); // 保存到数据库
        $this->pluginRepository->save($plugin, true);

        $this->addFlash('success', '配置已保存');
    }

    return $this->render('plugin/config.html.twig', [
        'form' => $form->createView()
    ]);
}
```

---

3️⃣ 渲染配置界面

```twig
{# templates/plugin/config.html.twig #}
{{ form_start(form) }}
    {{ form_widget(form) }}
    <button class="btn btn-primary">保存</button>
{{ form_end(form) }}
```

---

### ✅ 3. 插件之间的依赖管理

---

**目标：**  
插件 A 安装前检查插件 B 是否存在和启用。

---

**步骤：**

1️⃣ 在 `plugin.json` 中声明依赖：

```json
{
  "name": "PluginA",
  "requires": ["PluginB", "PluginC"]
}
```

---

2️⃣ 在安装和加载时校验

- 安装时检查：
```php
$required = $pluginData['requires'] ?? [];
foreach ($required as $req) {
    if (!$this->pluginRepository->findOneBy(['handle' => $req, 'enabled' => true])) {
        throw new \Exception("依赖插件 {$req} 未启用，无法安装");
    }
}
```

- 加载时检查（`PluginLoader`）：
```php
$enabledPluginIds = ...;
foreach ($enabledPluginIds as $pluginId) {
    $pluginData = ...;
    $required = $pluginData['requires'] ?? [];
    foreach ($required as $req) {
        if (!in_array($req, $enabledPluginIds)) {
            throw new \Exception("插件 {$pluginId} 依赖 {$req}，但未启用");
        }
    }
}
```

---

### 📦 总结

| 功能              | 技术点                                     |
|-------------------|-------------------------------------------|
| 安装/卸载         | zip 解压、文件系统、数据库记录              |
| 配置界面         | 动态表单、`plugin.json` schema、保存配置    |
| 依赖管理         | `plugin.json` requires 字段、加载时校验    |

---

