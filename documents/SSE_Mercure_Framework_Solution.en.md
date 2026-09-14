# Enterprise SSE (Server-Sent Events) & Mercure Real-time Communication Solution

> [中文](SSE_Mercure_Framework_Solution.md) | English

For the low-code architecture of `enterprise_framework`, this solution builds a generic, framework-level real-time communication infrastructure using the **Mercure Hub** built into **FrankenPHP**, delivering efficient and stable data sync and instant messaging.

## 1. Core Architecture

### 1.1 Tech Stack

- **Server**: [FrankenPHP](https://frankenphp.dev/) (Worker mode, `./public/index.php`)
- **Protocol**: [Mercure](https://mercure.rocks/) (SSE enhancement over HTTP/2; BoltDB for persistence)
- **Browser compatibility**: all modern browsers (Chrome, Firefox, Safari, Edge) natively support EventSource. IE requires a polyfill or fallback.
- **Backend**: Symfony Mercure Bundle + `MercureService` (`src/Service/Platform/MercureService.php`)
- **Frontend**: native `EventSource` + `ef-mercure.js` (`public/lib/ef/base/ef-mercure.js`)

### 1.2 Core Concept: Event-Driven UI

Instead of polling for data, the backend actively pushes "change events". The frontend listens to these events and automatically updates the DOM, shows notifications or refreshes components based on event type.

---

## 2. Backend Implementation

### 2.1 Generic `MercureService`

Located at `src/Service/Platform/MercureService.php`, the unified backend push entry supports multiple push modes:

- **Private push**: to a specific user (e.g. personal notifications)
- **Public push**: to all subscribers (e.g. entity sync messages)
- **Entity sync**: pushes changes for a specific entity; topic format `/entity/{entityName}/{id}`

```php
// src/Service/Platform/MercureService.php
class MercureService
{
    public function publish(string $topic, mixed $data, bool $private = true): string;

    public function publishEntitySync(string $entityName, string $id, string $action = 'update', array $changedFields = []): void;

    public function notifyUser(string $userId, string $title, string $message, string $level = 'info'): void;
}
```

### 2.2 Topic Design

- Private: `/user/{userId}/notifications`
- Entity sync: `/entity/{entityName}/{id}` (lowercase)
- Global events: `/global/{eventName}`

### 2.3 Security

- Publisher JWT signed by the hub secret
- Subscriber JWT authorizes the topics a client can listen to
- Private topics are only accessible to the owning user

---

## 3. Frontend Implementation

### 3.1 `ef-mercure.js`

Wraps native `EventSource` and provides:

- Automatic reconnection with exponential backoff
- Topic subscription management
- Event dispatch to registered handlers
- Connection state events (`connected`, `disconnected`, `reconnecting`)

```js
// usage
const client = new MercureClient('/.well-known/mercure?topic=/entity/...');
client.on('update', (data) => { /* update DOM */ });
```

### 3.2 Event-Driven UI Pattern

- Subscribe to entity topics on page load
- On `update` event, refresh the affected component
- Show toast notifications for user-level pushes
- Keep components in sync without full-page reloads

---

## 4. Integration with the Framework

### 4.1 Online Presence

- Heartbeat via Mercure to track online users
- `PresenceService` (`src/Service/Platform/PresenceService.php`) maintains presence state
- Admin UI shows online indicators in real time

### 4.2 Data Grid / Form Sync

- After entity changes, `publishEntitySync` broadcasts the change
- Other open tabs/pages refresh automatically
- Works with the universal entity cache listener to keep data consistent

### 4.3 AI Chat Progress

- Long-running AI operations publish progress events (e.g. `ai_chat_progress`)
- The editor updates the chat panel in real time

---

## 5. Deployment

- FrankenPHP exposes Mercure at `/.well-known/mercure`
- Configure the hub JWT secrets in the Caddyfile
- Enable `mercure` transport in the Caddyfile route

See `Caddyfile` in the project root for the current Mercure configuration.

---

## 6. Best Practices

- Use `notifyUser` for personal notifications, `publishEntitySync` for entity changes
- Keep topic names lowercase and namespaced
- Always provide a fallback for clients without SSE support
- Batch high-frequency updates (throttle/debounce) to avoid flooding the hub
