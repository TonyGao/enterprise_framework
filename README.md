## 狗狗框架

<div align="center">

![狗狗框架图片](/documents/conceptions/assets/dog215.png)

</div>

### 项目介绍

狗狗框架是一个基于 **Symfony 7.4 LTS**（2025.11 - 2029.11）的企业级低代码开发框架，致力于为企业应用提供完整的开发底座，实现快速、高质量、功能丰富、愉快的开发过程。

### 运行指南 (本地开发)

本项目使用 **FrankenPHP** 提供极速的 Worker 模式和内置的 Mercure 推送支持。

```bash
# 在项目根目录启动服务 (自动启动 Web 服务和 Mercure Hub)
./serve
```

* **Web 访问地址**: `http://localhost:8000`
* **Mercure 推送地址**: `http://localhost:8000/.well-known/mercure`

### 功能模块

#### 已实现

* **组织架构管理**
  * 集团/公司管理（树形结构，Gedmo Nested Set）
  * 部门管理（树形结构，动态属性表单）
  * 岗位管理、职级管理
  * 人员管理（完整 CRUD、导入导出、头像上传）
  * 组织架构 RESTful API
* **定时任务调度**
  * Cron 表达式解析，多处理器支持
  * 任务执行日志、日历视图（月/周/日）
  * 消息队列异步执行
* **系统日历**
  * 节假日管理、调休日、工作日配置
  * 节假日导入服务
* **文件存储系统**
  * 多存储后端（本地 / 阿里云 OSS / AWS S3）
  * 分块上传、断点续传
  * 图片自动优化（异步压缩、WebP 转换）
  * JS SDK、管理界面
* **安全认证**
  * WebAuthn 无密码登录（指纹/面容/安全密钥）
  * 密码策略管理（复杂度、过期、历史、重试锁定）
  * 三员分立权限模型（系统管理员、安全官、审计官）
  * 密码找回（邮箱 / 短信 / 密保问题 / Passkey）
  * 登录限流、会话管理
* **低代码平台引擎**
  * 动态实体模型（可视化创建字段、自动生成迁移）
  * 视图设计器（拖拽式可视化编辑器）
  * 数据表格配置、数据源管理
* **AI 集成**
  * 多 Provider 支持（阿里云百炼、LM Studio、OpenAI）
  * 智能体系统（编码助手、自然语言查询、密码策略解析、视觉识别）
* **实时通信**
  * Mercure SSE 实时推送
  * 在线状态监测（心跳 + EventStream）
* **邮件系统**
  * 多邮箱配置、邮件模板管理
  * 邮件功能绑定、HTML 安全过滤
* **审计日志**
  * 系统操作全链路审计
* **菜单管理**
  * 树形菜单、启用/禁用、YAML 静态化生成
* **UI 组件库 (SunUI)**
  * 25+ 组件（表格、表单、树、选择器、弹窗、抽屉、标签页、数据表格等）
  * 统一的 Twig 宏库、组件文档
* **国际化**
  * 中文 / 英文

#### 开发中

* [ ] 表单设计器
* [ ] API 接口管理
* [ ] 即时通讯 / 全局通知系统
* [ ] 虚拟助手（3D 交互）

### 第三方库

* <https://prismjs.com/index.html>
* <https://cs.symfony.com/>
* <https://github.com/symfony/panther>
* [LeaderLine](https://anseki.github.io/leader-line/)
* [Daterangepicker](https://www.daterangepicker.com/)
* [FrankenPHP](https://frankenphp.dev/) - 极速的现代 PHP 应用服务器 (内置 Mercure)
