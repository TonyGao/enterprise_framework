# Wechatian 微信 AI 通道集成文档

> 将微信（个人号）变成 AI Agent 与业务系统的双向通道，通过腾讯官方 ilink 机器人网关。
> 技术来源：[Wechatian](https://github.com/laruence/wechatian)（MIT 协议，laruence 出品）
> 用途：本文件为后续在项目中引入"微信 ↔ 系统"能力的落地参考。

---

## 1. 技术概述

**一句话**：Wechatian 是一个把微信变为双向消息通道的桥接组件。它绕过任何第三方中转服务，通过腾讯官方 **ilink 机器人网关**（`ilinkai.weixin.qq.com`）与微信服务器通信，实现：

- **收**：微信里的文字、图片、文件、视频、语音、公众号文章链接，实时进入系统
- **发**：系统/AI Agent 往本地发件箱丢一个文件，自动发到绑定的微信

**关键特性**：
- 走**官方协议**，无逆向 hack，不需要自建中转服务器
- **隐私**：消息内容只存在于你自己的系统，不经任何第三方
- **零集成成本**：不暴露 API，任何能写文件的程序（shell、Python、AI Agent）都能发微信
- **个人微信扫码绑定**，无需企业认证

**核心架构链路**：

```
微信客户端 ←→ 腾讯ilink网关(ilinkai.weixin.qq.com) ←→ [Wechatian核心] ←→ 你的业务系统/知识库/AI Agent
```

---

## 2. 网关协议（集成核心）

### 2.1 基础信息

| 项目 | 值 |
|------|-----|
| 网关地址 `baseUrl` | `https://ilinkai.weixin.qq.com` |
| CDN 地址 `cdnBase` | `https://novac2c.cdn.weixin.qq.com/c2c` |
| 认证方式 | `Authorization: Bearer <token>` |
| 认证头类型 | `AuthorizationType: ilink_bot_token` |
| 附加头 | `X-WECHAT-UIN: <random base64>` |
| 频道标识 | `base_info.channel_version: "wechatian-weixin/1.0"` |
| 会话过期错误码 | `errcode = -14`（触发重新扫码） |

### 2.2 协议流程总览

```
┌────────┐   ①扫码登录    ┌────────────────┐
│  微信   │ ←──────────→ │   ilink 网关    │
│ 客户端  │   ②长轮询    │ ilinkai.weixin │
│        │  ③收发消息    │    .qq.com     │
└────────┘                └───────┬────────┘
                                  │ ④getupdates/sendmessage
                            ┌─────▼─────┐
                            │ 业务系统  │
                            └───────────┘
```

### 2.3 扫码登录（一次性）

1. **获取二维码**
   ```
   GET {baseUrl}/ilink/bot/get_bot_qrcode?bot_type=3
   ```
   - 返回 `qrcode`（key）和 `qrcode_img_content`（二维码 URL）
   - 二维码 TTL 约 120 秒，需主动刷新（建议 80 秒刷新一次，最多 3 次）

2. **轮询扫码状态**
   ```
   GET {baseUrl}/ilink/bot/get_qrcode_status?qrcode={qrcode}
   请求头: iLink-App-ClientVersion: 1
   ```
   - 状态：`wait`（等待）→ 扫描后确认 → 成功返回 `token`、`botId`、`baseUrl`、`scannedUser`

3. **绑定规则**：只有扫码的那个微信号是合法发送方；其余账号消息一律忽略

4. **重要前置条件**：绑定账号**必须先给 bot 主动发一条消息**，网关才会下发发送凭据（`context_token`），否则 bot 无法主动发送

### 2.4 接收消息（长轮询）

```
POST {baseUrl}/ilink/bot/getupdates
Body: {
  "get_updates_buf": "<cursor>",       // 首次为空，之后用上次返回的游标
  "base_info": { "channel_version": "..." }
}
```

- **长轮询超时约 35~45 秒**（网络超时不算错误，返回空即可继续）
- 返回 `msgs[]` + `get_updates_buf`（下轮游标）
- 消息类型：`MSG_TYPE_USER`（收到的用户消息），`MSG_TYPE_BOT`（自己发的，忽略）
- 每条消息含 `context_token`，**必须缓存**，用于后续回复该用户

**消息结构要点**：
- `from_user_id`：发送者 ID
- `item_list[]`：消息内容项，类型包括文本(text)、图片(image)、文件(file)、视频(video)、语音(voice)
- 文本通过 `text_item.text` 获取
- 媒体项含 `media.encrypt_query_param` + `media.aes_key`，需**下载并 AES-ECB 解密**

### 2.5 发送消息

**发送前置条件**：必须有该用户的 `context_token`（即对方先发过消息）

#### 发送文本
```
POST {baseUrl}/ilink/bot/sendmessage
Body: {
  "msg": {
    "from_user_id": "",
    "to_user_id": "<对方user id>",
    "client_id": "wct-<random hex6>",
    "message_type": "bot",
    "message_state": "finished",
    "item_list": [{ "type": "text", "text_item": { "text": "<内容>" } }],
    "context_token": "<缓存的token>"
  },
  "base_info": { "channel_version": "..." }
}
```
- 文本自动分块：每 3800 字符，块间 100ms 间隔
- 成功判定：`ret == 0` 或 `errcode == 0` 或 HTTP 200 且无 errmsg

#### 发送媒体/文件（≤100MB）
1. 生成随机 16 字节 AES key `key` + 16 字节随机 `filekey`
2. **AES-ECB 加密**文件内容（用 key）
3. 获取上传地址：
   ```
   POST {baseUrl}/ilink/bot/getuploadurl
   Body: { "filekey": ..., "aeskey": "<key的hex>", "file_size": ..., "base_info": {...} }
   ```
4. 上传密文到 CDN：
   ```
   POST {cdnBase}/upload?encrypted_query_param={upload_param}&filekey={filekey}
   ```
5. 发送消息引用该媒体（`item_list` 中 `media.aes_key` 需用 **base64(hex(key))** 格式）

**CDN 限制**：单文件 ≤ 100MB

### 2.6 认证头

```http
Authorization: Bearer <token>
AuthorizationType: ilink_bot_token
X-WECHAT-UIN: <random 32bit int as base64>
Content-Type: application/json
```

---

## 3. 接入模式

### 3.1 模式 A：复用 Wechatian 插件（Obsidian 场景）

- 适用于已有 Obsidian 知识库、让 AI Agent（Claudian/Claude Code 等）工作流使用
- 直接在 Obsidian 社区插件市场安装
- 收发箱机制：`outbox/` 放文件→发微信；`inbox/YYYY-MM-DD.md` 记对话

### 3.2 模式 B：Headless 集成（推荐用于业务系统）

**核心代码是独立 TypeScript 库**（`src/core/ilink.ts`、`types.ts`、`crypto.ts`、`http.ts`），可脱离 Obsidian 单独部署为：

- 独立 Web 服务 / 后台守护进程
- Python/Node 服务的微服务模块
- 与任何 AI Agent 框架对接

**Headless 集成清单**：
| 组件 | 职责 |
|------|------|
| `IlinkClient` | 长轮询、发送文本/媒体、CDN 上传 |
| `crypto.ts` | AES-ECB 加解密、AES key 解析、MD5 |
| `StateStore` | 持久化 cursor / token / context_token |
| 发件箱/收件箱 | 文件式接口，便于任何程序接入 |

---

## 4. 限制与约束（务必知晓）

| 限制 | 说明 |
|------|------|
| **主动发送限流** | 网关限制 bot 主动发消息（约每天数条）。适合**通知/拍板**，不适合高频聊天 |
| **单设备** | 同一微信账号同时只建议一台设备在线，多端轮询会争抢消息 |
| **不支持转发** | bot 无法接收"转发的"文章/文件，需发链接或文件消息 |
| **桌面端** | 插件版仅支持桌面 Obsidian 1.13+（移动端长轮询不稳） |
| **先发后收** | 绑定账号必须先给 bot 发消息，才能解锁 bot 主动发送 |
| **会话过期** | `errcode = -14` 需重新扫码（约每小时也可能出现，插件有暂停重试机制） |
| **一对一** | 通道是"给自己绑定账号"的点对点，不指定收件人 |
| **隐私合规** | 消息内容在本地方处理，但请评估所在组织对微信内个人数据的合规要求 |

---

## 5. 其他项目落地步骤

### 5.1 前置准备
1. 一个可用的个人微信账号
2. 部署环境（任意服务器/本机，资源占用极小）
3. Node.js 18+（源码基于 TS/esbuild 构建）

### 5.2 集成步骤
```
1. 拉取代码：git clone https://github.com/laruence/wechatian.git
2. 提取 core 模块（ilink/crypto/http/types）为独立依赖
3. 实现 StateStore：持久化 cursor/token/context_token（可存 JSON/DB/Redis）
4. 实现收发接口：
   - 收：长轮询 getupdates → 解析文本/下载解密媒体 → 交给业务逻辑
   - 发：业务逻辑写 outbox 文件 → 插件轮询消费 → sendmessage
5. 扫码绑定（首次）
6. 对接 AI Agent：提供 Agent.md 说明协议即可
```

### 5.3 推荐部署拓扑（一台服务器）

```
       微信(个人号)
          │
   腾讯 ilink 网关
          │
   ┌──────▼──────┐
   │  Headless   │ ← Node/TS 守护进程
   │  Wechatian  │   长轮询 + 收发箱
   └──────┬──────┘
          │ 文件通道 / 内部API
   ┌──────▼──────┐
   │  AI Agent   │ ← 本地模型 或 云端API
   │ / 业务系统  │
   └─────────────┘
```

---

## 6. 参考

- 项目仓库：https://github.com/laruence/wechatian
- 原始文章：https://mp.weixin.qq.com/s/t0elE5Ha0Ulbt1vEOXKhOw
- 授权协议：MIT
- 相关概念：CC Connect（灵感来源）、Obsidian + Claudian 个人 AI 助理

---

*文档基于 Wechatian 源码（`src/core/*`）与发布文章整理，字段/端点以仓库最新代码为准。*
