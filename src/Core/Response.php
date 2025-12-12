<?php
declare(strict_types=1);

namespace App\Core;

/**
 * HTTP 响应封装类
 * 
 * CFC V7.7 规范：
 * - 统一处理 Header, JSON, SSE 等输出
 * - 支持链式调用
 * - 收拢所有输出逻辑
 */
class Response
{
    private int $statusCode = 200;
    private array $headers = [];
    private mixed $content = '';
    private bool $sent = false;

    /**
     * 创建 JSON 响应
     */
    public static function json(array $data, int $status = 200): self
    {
        $response = new self();
        $response->statusCode = $status;
        $response->headers['Content-Type'] = 'application/json; charset=utf-8';
        $response->content = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        return $response;
    }

    /**
     * 创建成功响应
     */
    public static function success(mixed $data = null, string $message = ''): self
    {
        return self::json([
            'success' => true,
            'data' => $data,
            'message' => $message,
        ]);
    }

    /**
     * 创建错误响应
     */
    public static function error(string $message, int $status = 400, mixed $data = null): self
    {
        return self::json([
            'success' => false,
            'error' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * 创建 404 响应
     */
    public static function notFound(string $message = 'Not Found'): self
    {
        return self::error($message, 404);
    }

    /**
     * 创建 SSE 响应（特殊处理）
     * 注意：SSE 响应由 SSEResponse 类单独处理，这里返回空响应
     */
    public static function sse(): self
    {
        $response = new self();
        $response->content = null; // 标记为 SSE，不输出内容
        return $response;
    }

    /**
     * 设置状态码
     */
    public function status(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    /**
     * 设置响应头
     */
    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * 设置内容
     */
    public function content(mixed $content): self
    {
        $this->content = $content;
        return $this;
    }

    /**
     * 发送响应
     */
    public function send(): void
    {
        if ($this->sent) {
            return;
        }

        // SSE 响应不在这里处理
        if ($this->content === null) {
            $this->sent = true;
            return;
        }

        // 发送状态码
        http_response_code($this->statusCode);

        // 发送响应头
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        // 发送内容
        if ($this->content !== '') {
            echo $this->content;
        }

        $this->sent = true;
    }

    /**
     * 获取状态码
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * 获取内容
     */
    public function getContent(): mixed
    {
        return $this->content;
    }

    /**
     * 是否已发送
     */
    public function isSent(): bool
    {
        return $this->sent;
    }
}
