/**
 * ef-mercure.js
 * 企业框架通用 Mercure (SSE) 客户端库
 */
(function ($) {
  'use strict';

  const EF_MERCURE = {
    eventSource: null,
    topics: new Set(),
    listeners: {},
    connectTimer: null,
    config: {
      hubUrl: $('meta[name="mercure-hub-url"]').attr('content'),
    },

    /**
     * 初始化 Mercure 连接
     * @param {Object} options
     */
    init: function (options = {}) {
      if (!this.config.hubUrl) {
        console.warn('Mercure Hub URL not found in meta tags.');
        return;
      }

      if (options.topics) {
        options.topics.forEach((t) => this.topics.add(t));
      }

      this.connect();
      this._bindAutoSync();
      this._observeDOM(); // 开启 DOM 监听，自动处理动态加载的内容
    },

    /**
     * 建立连接 (带防抖)
     */
    connect: function () {
      if (this.connectTimer) {
        clearTimeout(this.connectTimer);
      }

      this.connectTimer = setTimeout(() => {
        this._doConnect();
      }, 100); // 100ms 防抖，合并短时间内的多次订阅请求
    },

    /**
     * 实际执行连接逻辑
     */
    _doConnect: function () {
      if (this.eventSource) {
        this.eventSource.close();
        this.eventSource = null;
      }

      if (this.topics.size === 0) {
        return;
      }

      const url = new URL(this.config.hubUrl);
      this.topics.forEach((topic) => {
        url.searchParams.append('topic', topic);
      });

      console.log(
        `[Mercure] Connecting to Hub with ${this.topics.size} topics...`
      );
      this.eventSource = new EventSource(url, { withCredentials: true });

      this.eventSource.onmessage = (event) => {
        this._handleReconnection(); // 只要收到任何消息，说明连接是通的
        try {
          const data = JSON.parse(event.data);
          this._handleMessage(data);
        } catch (e) {
          console.error('Failed to parse Mercure message:', e, event.data);
        }
      };

      this.eventSource.onerror = (err) => {
        // 浏览器会自动重连
        if (
          this.eventSource.readyState === EventSource.CLOSED ||
          this.eventSource.readyState === EventSource.CONNECTING
        ) {
          this._handleDisconnection();
        }
      };
    },

    /**
     * 订阅新主题
     * @param {string|string[]} newTopics
     */
    subscribe: function (newTopics) {
      const topics = Array.isArray(newTopics) ? newTopics : [newTopics];
      let changed = false;
      topics.forEach((t) => {
        if (t && !this.topics.has(t)) {
          this.topics.add(t);
          changed = true;
        }
      });

      if (changed) {
        this.connect(); // 重新连接以订阅新主题
      }
    },

    /**
     * 开启 DOM 监听，检测动态添加的 data-ef-sync 元素
     */
    _observeDOM: function () {
      const self = this;
      const observer = new MutationObserver((mutations) => {
        let hasNewSync = false;
        mutations.forEach((mutation) => {
          mutation.addedNodes.forEach((node) => {
            if (node.nodeType === 1) {
              // Element
              const $node = $(node);
              if (
                $node.attr('data-ef-sync') ||
                $node.find('[data-ef-sync]').length > 0
              ) {
                hasNewSync = true;
              }
            }
          });
        });

        if (hasNewSync) {
          self._bindAutoSync();
        }
      });

      observer.observe(document.body, { childList: true, subtree: true });
    },

    /**
     * 监听特定事件
     * @param {string} type
     * @param {Function} callback
     */
    on: function (type, callback) {
      if (!this.listeners[type]) {
        this.listeners[type] = [];
      }
      this.listeners[type].push(callback);
    },

    /**
     * 内部消息处理
     */
    _handleMessage: function (data) {
      // 1. 触发通过 .on() 注册的监听器
      if (data.type && this.listeners[data.type]) {
        this.listeners[data.type].forEach((cb) => cb(data));
      }

      // 2. 处理自动同步 (Sync)
      if (data.type === 'sync') {
        this._performAutoSync(data);
      }

      // 3. 处理全局通知
      if (data.type === 'notification') {
        this._showNotification(data);
      }
    },

    /**
     * 自动同步 DOM
     */
    _performAutoSync: function (data) {
      const syncKey = `${data.entity.toLowerCase()}:${data.id}`;
      $(`[data-ef-sync="${syncKey}"]`).each(function () {
        const $el = $(this);
        const refreshUrl = $el.data('ef-sync-url');

        if (refreshUrl) {
          // 如果指定了刷新 URL，则局部加载
          $el.load(refreshUrl);
        } else {
          // 否则触发一个自定义事件，让组件自己决定怎么刷新
          $el.trigger('ef:sync', [data]);
        }
      });
    },

    /**
     * 显示系统通知
     */
    _showNotification: function (data) {
      if (window.ui && window.ui.alert) {
        window.ui.alert.show({
          title: data.title,
          content: data.content,
          type: data.level || 'info',
          icon: data.icon,
        });
      }
    },

    /**
     * 绑定自动同步检测
     */
    _bindAutoSync: function () {
      const self = this;
      // 扫描页面上所有的同步声明，自动订阅主题
      $('[data-ef-sync]').each(function () {
        const syncValue = $(this).data('ef-sync');
        const [entity, id] = syncValue.split(':');
        if (entity && id) {
          self.subscribe(`/entity/${entity.toLowerCase()}/${id}`);
        }
      });
    },

    presenceSource: null,
    legacyHeartbeatTimer: null,
    watchdogTimer: null,
    reconnectProbeTimer: null,
    lastHeartbeatReceived: null,
    isDisconnected: false,
    config: {
      hubUrl: $('meta[name="mercure-hub-url"]').attr('content'),
      heartbeatMode:
        $('meta[name="mercure-heartbeat-mode"]').attr('content') || 'ajax', // 'sse' or 'ajax'
    },

    /**
     * 内部处理：显示断线提醒并启动受控重连探测
     */
    _handleDisconnection: function () {
      if (this.isDisconnected) return;
      this.isDisconnected = true; // 立即锁定状态，防止重复触发

      console.error('[Presence] SERVER DISCONNECTED! Entering probe mode...');

      // 1. 立即关闭所有 EventSource，防止浏览器无限重试
      this.disconnect();
      if (this.presenceSource) {
        this.presenceSource.close();
        this.presenceSource = null;
      }

      // 2. 只在真正需要时显示提醒（不显示红色横幅，只用 console 记录）
      console.warn('[Presence] Connection lost. Will auto-reconnect...');

      // 3. 启动低频探测逻辑
      this._startReconnectionProbe();
    },

    /**
     * 内部处理：恢复连接
     */
    _handleReconnection: function () {
      this.lastHeartbeatReceived = Date.now();

      if (!this.isDisconnected) return;
      this.isDisconnected = false;

      console.log('[Presence] SERVER RECONNECTED! Restoring connections...');

      // 1. 清除提醒
      $('#ef-disconnection-notice').remove();

      // 2. 停止探测和旧的看门狗
      if (this.reconnectProbeTimer) {
        clearInterval(this.reconnectProbeTimer);
        this.reconnectProbeTimer = null;
      }
      if (this.watchdogTimer) {
        clearInterval(this.watchdogTimer);
        this.watchdogTimer = null;
      }

      // 3. 延迟重新初始化连接
      setTimeout(() => {
        this.connect();
        this.startHeartbeat();
      }, 1000);
    },

    /**
     * 启动低频重连探测
     */
    _startReconnectionProbe: function () {
      if (this.reconnectProbeTimer) clearInterval(this.reconnectProbeTimer);

      this.reconnectProbeTimer = setInterval(() => {
        console.debug('[Mercure] Probing server status...');
        // 发送一个极轻量级的 HEAD 请求探测服务器
        $.ajax({
          url: '/',
          method: 'HEAD',
          cache: false, // 禁用缓存，确保真实探测服务器
          timeout: 2000,
          success: () => {
            this._handleReconnection();
          },
          error: () => {
            // 依然失败
          },
        });
      }, 5000); // 缩短探测间隔至 5 秒，让恢复感知更灵敏
    },

    /**
     * 断开所有连接
     */
    disconnect: function () {
      if (this.eventSource) {
        this.eventSource.close();
        this.eventSource = null;
      }
      if (this.connectTimer) {
        clearTimeout(this.connectTimer);
        this.connectTimer = null;
      }
    },

    /**
     * 开启在线状态维持
     * 支持 'sse' (开发推荐) 或 'ajax' (生产推荐) 两种模式
     */
    startHeartbeat: function (options = {}) {
      const mode = options.mode || this.config.heartbeatMode;
      const interval = options.interval || 30000;

      // 清理旧心跳和监控器
      if (this.presenceSource) {
        this.presenceSource.close();
        this.presenceSource = null;
      }
      if (this.legacyHeartbeatTimer) {
        clearInterval(this.legacyHeartbeatTimer);
        this.legacyHeartbeatTimer = null;
      }
      if (this.watchdogTimer) {
        clearInterval(this.watchdogTimer);
        this.watchdogTimer = null;
      }

      // 初始化最后接收时间
      this.lastHeartbeatReceived = Date.now();

      if (mode === 'sse' && typeof EventSource !== 'undefined') {
        this._startSSEHeartbeat();
      } else {
        this._startAJAXHeartbeat(interval);
      }

      // 启动“看门狗”监控逻辑
      this._startWatchdog();
    },

    /**
     * 启动监控看门狗：如果长时间没有收到任何信号，判定为断线
     */
    _startWatchdog: function () {
      const timeout = 35000;
      if (this.watchdogTimer) clearInterval(this.watchdogTimer);

      console.log('[Presence] Watchdog started.');

      // 记录看门狗启动时间，前 10 秒内不触发 readyState 检查（给连接建立的时间）
      const watchdogStartTime = Date.now();

      this.watchdogTimer = setInterval(() => {
        if (this.isDisconnected) return;

        const now = Date.now();
        const diff = now - this.lastHeartbeatReceived;
        const timeSinceStart = now - watchdogStartTime;

        // 1. 检查数据流是否超时
        if (diff > timeout) {
          console.warn(
            `[Mercure] Watchdog timeout: ${diff}ms since last signal. Triggering disconnection...`
          );
          this._handleDisconnection();
          return;
        }

        // 2. 深度检查 readyState (仅在启动 10 秒后执行，防止刚连上就被判定断线)
        if (timeSinceStart > 10000) {
          const sse1 = this.eventSource;
          const sse2 = this.presenceSource;

          // 如果任何一个连接存在但不是 OPEN 状态
          if (
            (sse1 && sse1.readyState !== EventSource.OPEN) ||
            (sse2 && sse2.readyState !== EventSource.OPEN)
          ) {
            // 允许 5 秒的重连缓冲期，超过则判定断线
            if (diff > 5000) {
              console.warn(
                '[Mercure] One or more SSE connections are not in OPEN state. Triggering disconnection...'
              );
              this._handleDisconnection();
            }
          }
        }
      }, 2000); // 提高检查频率至 2 秒
    },

    /**
     * 内部：启动 SSE 心跳
     */
    _startSSEHeartbeat: function () {
      const url = new URL('/api/presence/stream', window.location.origin);
      url.searchParams.append('_t', Date.now()); // 增加时间戳，强制建立新连接，防止缓存挂起

      console.log('[Presence] Starting SSE heartbeat stream...');
      this.presenceSource = new EventSource(url.toString(), {
        withCredentials: true,
      });

      this.presenceSource.onopen = () => {
        console.log('[Presence] SSE heartbeat connected.');
        this._handleReconnection();
      };

      this.presenceSource.onmessage = (e) => {
        this._handleReconnection();
      };

      this.presenceSource.onerror = (err) => {
        // EventSource 在重连时也会触发 onerror
        if (
          this.presenceSource.readyState === EventSource.CLOSED ||
          this.presenceSource.readyState === EventSource.CONNECTING
        ) {
          this._handleDisconnection();
        }
      };

      window.addEventListener('beforeunload', () => {
        if (this.presenceSource) {
          this.presenceSource.close();
        }
      });
    },

    /**
     * 内部：启动 AJAX 心跳 (短连接，生产推荐，释放服务器 Worker)
     */
    _startAJAXHeartbeat: function (intervalMs = 30000) {
      console.log(
        `[Presence] Starting AJAX heartbeat (Interval: ${intervalMs}ms)`
      );
      const sendHeartbeat = () => {
        const url = '/api/presence/heartbeat';
        if (window.ef && window.ef.ajax) {
          window.ef.ajax.post(
            url,
            {},
            {
              quiet: true,
              success: () => this._handleReconnection(),
              error: () => this._handleDisconnection(),
            }
          );
        } else {
          $.ajax({
            url: url,
            method: 'POST',
            success: () => this._handleReconnection(),
            error: () => this._handleDisconnection(),
          });
        }
      };

      sendHeartbeat();
      this.legacyHeartbeatTimer = setInterval(sendHeartbeat, intervalMs);
    },
  };

  // 暴露给全局 EF 命名空间
  window.EF = window.EF || {};
  window.EF.Mercure = EF_MERCURE;
})(jQuery);
