<?php

namespace App\Service\Platform\Llm;

/**
 * 把 LLM 网关异常格式化成对用户友好、可操作的提示。
 * 原始技术细节（响应体、堆栈）仅记录日志，不直接暴露给用户界面。
 *
 * Formats LLM gateway errors into user-friendly, actionable messages.
 * Raw technical detail (body, stack) is logged only, not shown in the UI.
 */
class LlmErrorFormatter
{
    public const MSG_PREFIX = 'msg.llm.error.';

    public static function friendlyMessage(string $providerName, int $statusCode, string $body = ''): string
    {
        // statusCode 0 表示"连接失败/超时"这类非 HTTP 响应
        if ($statusCode === 0) {
            return 'msg.llm.error.connection';
        }

        return match (true) {
            $statusCode === 401, $statusCode === 403 => 'msg.llm.error.auth',
            $statusCode === 404 => 'msg.llm.error.not_found',
            $statusCode === 429 => 'msg.llm.error.rate_limited',
            $statusCode >= 500 && $statusCode <= 599 => 'msg.llm.error.server',
            default => 'msg.llm.error.request',
        };
    }

    /**
     * 从网关抛出的异常里提取"面向用户"的提示 key。
     * LlmException 携带 statusCode 上下文；其它异常（连接错误）归为网络问题。
     */
    public static function keyFor(\Throwable $e): string
    {
        if ($e instanceof LlmException) {
            $code = $e->context['statusCode'] ?? 0;
            if (is_int($code)) {
                return self::friendlyMessage('', $code);
            }
            return 'msg.llm.error.connection';
        }

        // Symfony HttpClient 传输异常（连接拒绝/超时/DNS 等）
        if (str_contains(get_class($e), 'TransportException') || str_contains(get_class($e), 'HttpException')) {
            return 'msg.llm.error.connection';
        }

        return 'msg.llm.error.unknown';
    }
}
