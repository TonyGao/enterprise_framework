[![English](https://img.shields.io/badge/lang-English-blue.svg)](README.md)
[![中文](https://img.shields.io/badge/lang-中文-red.svg)](README.zh-CN.md)

---

## Dog Framework (狗狗框架)

<div align="center">

![Dog Framework Logo](/documents/conceptions/assets/dog215.png)

</div>

### Introduction

**Dog Framework** (狗狗框架) is an enterprise low-code development framework built on **Symfony 7.4 LTS** (2025.11 - 2029.11). It provides a complete development foundation for enterprise applications, enabling fast, high-quality, feature-rich, and enjoyable development.

### Installation

```bash
composer create-project doggy/skeleton my-app
cd my-app
```

### FrankenPHP Setup

The `./serve` script requires FrankenPHP for the HTTP server and Mercure push support. Choose one method:

**Option 1: Download binary (recommended)**

Download the binary for your platform from https://frankenphp.dev, rename it to `frankenphp` and place it in the project root:

```bash
chmod +x frankenphp
```

**Option 2: Homebrew (macOS)**

```bash
brew install frankenphp
```

### Initialization

```bash
# Edit .env.local to configure your database connection
# Then run:
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate -n
php bin/console ef:init-admin-menu
php bin/console ef:init-storage-config
```

### Running (Development)

```bash
./serve
```

- **Web**: `http://localhost:8000`
- **Mercure Hub**: `http://localhost:8000/.well-known/mercure`

### Features

#### Implemented

- **Organization Management**
  - Group/Company management (tree structure, Gedmo Nested Set)
  - Department management (tree structure, dynamic attribute forms)
  - Position & Position Level management
  - Employee management (full CRUD, import/export, avatar upload)
  - Organization RESTful API
- **Task Scheduler**
  - Cron expression parsing, multi-handler support
  - Execution logs, calendar view (month/week/day)
  - Async execution via message queue
- **System Calendar**
  - Holiday, makeup day, workday management
  - Holiday import service
- **File Storage System**
  - Multiple backends (Local / Alibaba Cloud OSS / AWS S3)
  - Chunked upload, resume from break
  - Auto image optimization (async compression, WebP conversion)
  - JS SDK, admin interface
- **Security & Authentication**
  - WebAuthn passwordless login (fingerprint/face/security key)
  - Password policy (complexity, expiry, history, retry lockout)
  - Three Officers RBAC model (Sys Admin, Security Officer, Auditor)
  - Password reset (email / SMS / security questions / Passkey)
  - Login throttling, session management
- **Low-Code Platform Engine**
  - Dynamic entity model (visual field creation, auto migration)
  - View designer (drag & drop visual editor)
  - DataGrid configuration, data source management
- **AI Integration**
  - Multi-provider (Alibaba Cloud Bailian, LM Studio, OpenAI)
  - Agent system (coding assistant, NLQ, password policy parser, vision)
- **Real-time Communication**
  - Mercure SSE push
  - Online presence monitoring (heartbeat + EventStream)
- **Email System**
  - Multi-mailbox config, email template management
  - Function binding, HTML security sanitization
- **Audit Log**
  - Full system operation audit trail
- **Menu Management**
  - Tree menu, enable/disable, YAML static generation
- **UI Component Library (SunUI)**
  - 25+ components (Table, Form, Tree, Selector, Modal, Drawer, Tabs, DataGrid, etc.)
  - Unified Twig macro library, component documentation
- **Internationalization**
  - Chinese / English

#### In Progress

- [ ] Form Designer
- [ ] API Management
- [ ] Instant Messaging / Global Notification System
- [ ] Virtual Assistant (3D interactive)

### Third-Party Libraries

- <https://prismjs.com/index.html>
- <https://cs.symfony.com/>
- <https://github.com/symfony/panther>
- [LeaderLine](https://anseki.github.io/leader-line/)
- [Daterangepicker](https://www.daterangepicker.com/)
- [FrankenPHP](https://frankenphp.dev/) - Modern application server for PHP (built-in Mercure)
