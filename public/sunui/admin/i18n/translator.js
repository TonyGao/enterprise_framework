/**
 * 前端国际化翻译助手 / Frontend i18n translation helper.
 *
 * 读取服务端注入的 window.__I18N__（当前语言文案字典），提供 t() 翻译函数。
 * Reads the server-injected window.__I18N__ (current-locale dictionary) and
 * exposes a t() translate function.
 */
(function (window) {
    'use strict';

    var DICT = window.__I18N__ || {};
    var LOCALE = window.__LOCALE__ || 'zh_CN';

    /**
     * 翻译 / Translate a message key.
     *
     * @param {string} key    文案 key / message key
     * @param {Object} [params] 占位参数（{name: 'x'} -> :name）/ placeholder params
     * @returns {string}
     */
    function t(key, params) {
        var msg = DICT[key] || key;
        if (params) {
            Object.keys(params).forEach(function (k) {
                msg = msg.split(':' + k).join(params[k]);
            });
        }
        return msg;
    }

    /** 当前语言 / Current locale */
    t.locale = LOCALE;

    /**
     * 从 API 错误响应取用户可读消息（优先翻译 code，回退 message）/
     * Resolve a user-readable message from an API error response
     * (prefer translating `code`, fall back to `message`).
     *
     * @param {object} xhr XMLHttpRequest / jQuery 响应
     * @returns {string}
     */
    function apiMsg(xhr) {
        var d = (xhr && xhr.responseJSON) ? xhr.responseJSON : xhr;
        if (d) {
            if (d.code && typeof d.code === 'string') {
                var tr = t(d.code);
                if (tr !== d.code) return tr;
            }
            if (d.message && typeof d.message === 'string') {
                // 若 message 本身是翻译 key，则翻译它
                var trm = t(d.message);
                if (trm !== d.message && trm.indexOf(' ') === -1) return trm;
                return d.message;
            }
        }
        return t('js.error');
    }

    window.t = t;
    window.apiMsg = apiMsg;
    window.__I18N__ = DICT;
})(window);
