<?php
declare(strict_types=1);

namespace App\Core;

/**
 * SSE 流式响应工具类
 * 
 * CFC V7.7 规范：
 * - 统一处理 Server-Sent Events 输出
 * - 禁止在其他地方使用原生 header/echo/flush
 * - 所有 SSE 输出必须通过此类
 * 
 * @package App\Core
 */
class SSEResponse
{
    private static bool $initialized = false;

    /**
     * 初始化 SSE 连接
     * 设置必要的响应头并禁用缓冲
     */
    public static function init(): void
    {
        if (self::$initialized || headers_sent()) {
            return;
        }

        // 设置 SSE 响应头
        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no'); // Nginx 禁用缓冲

        // 清空所有输出缓冲
        while (ob_get_level()) {
            ob_end_clean();
        }

        // 设置无限执行时间
        set_time_limit(0);
        ignore_user_abort(false);

        self::$initialized = true;
    }

    /**
     * 发送 SSE 事件
     * 
     * @param string $type 事件类型
     * @param array $data 事件数据
     * @return bool 是否发送成功
     */
    public static function send(string $type, array $data = []): bool
    {
        if (!self::$initialized) {
            self::init();
        }

        if (self::isDisconnected()) {
            return false;
        }

        $data['type'] = $type;
        echo "data: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";

        if (ob_get_level()) {
            ob_flush();
        }
        flush();

        return true;
    }

    /**
     * 发送思考状态
     */
    public static function thinking(string $agentId, string $agentName): bool
    {
        return self::send('thinking', [
            'agent' => $agentId,
            'name' => $agentName,
        ]);
    }

    /**
     * 发送内容片段
     */
    public static function chunk(string $agentId, string $content): bool
    {
        return self::send('chunk', [
            'agent' => $agentId,
            'content' => $content,
        ]);
    }

    /**
     * 发送完成状态
     */
    public static function complete(string $agentId): bool
    {
        return self::send('complete', [
            'agent' => $agentId,
        ]);
    }

    /**
     * 发送错误
     */
    public static function error(string $message): bool
    {
        return self::send('error', [
            'message' => $message,
        ]);
    }

    /**
     * 结束 SSE 连接
     */
    public static function end(): void
    {
        self::send('done', [
            'timestamp' => time(),
        ]);
        self::$initialized = false;
    }

    /**
     * 检查客户端是否断开
     */
    public static function isDisconnected(): bool
    {
        return connection_status() !== CONNECTION_NORMAL || connection_aborted();
    }

    /**
     * 重置状态（用于测试）
     */
    public static function reset(): void
    {
        self::$initialized = false;
    }
}
