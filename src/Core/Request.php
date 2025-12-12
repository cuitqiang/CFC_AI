<?php
declare(strict_types=1);

namespace App\Core;

/**
 * HTTP 请求封装类
 * 
 * CFC V7.7 规范：
 * - 封装 $_GET, $_POST, php://input
 * - 解析 REQUEST_URI 用于 RESTful 路由
 * - 提供统一的参数访问接口
 */
class Request
{
    private string $method;
    private string $uri;
    private string $path;
    private array $query;
    private array $post;
    private array $headers;
    private ?array $json = null;

    private function __construct()
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->uri = $_SERVER['REQUEST_URI'] ?? '/';
        $this->path = $this->parsePath($this->uri);
        $this->query = $_GET;
        $this->post = $_POST;
        $this->headers = $this->parseHeaders();
    }

    /**
     * 捕获当前请求（单例模式）
     */
    public static function capture(): self
    {
        static $instance = null;
        if ($instance === null) {
            $instance = new self();
        }
        return $instance;
    }

    /**
     * 解析 URI 中的路径部分（去除 query string）
     */
    private function parsePath(string $uri): string
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        
        // 移除入口文件名（如 /api.php 或 /index.php）
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $scriptDir = dirname($scriptName);
        
        if ($scriptDir !== '/' && str_starts_with($path, $scriptDir)) {
            $path = substr($path, strlen($scriptDir));
        }
        
        // 确保以 / 开头
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }
        
        // 移除末尾的 /（除了根路径）
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }
        
        return $path;
    }

    /**
     * 解析请求头
     */
    private function parseHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', substr($key, 5));
                $headers[$name] = $value;
            }
        }
        return $headers;
    }

    /**
     * 获取请求方法
     */
    public function method(): string
    {
        return $this->method;
    }

    /**
     * 获取请求路径
     */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * 获取完整 URI
     */
    public function uri(): string
    {
        return $this->uri;
    }

    /**
     * 获取 Query 参数
     */
    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /**
     * 获取所有 Query 参数
     */
    public function allQuery(): array
    {
        return $this->query;
    }

    /**
     * 获取 POST 参数
     */
    public function post(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $default;
    }

    /**
     * 获取 JSON Body
     */
    public function json(string $key = null, mixed $default = null): mixed
    {
        if ($this->json === null) {
            $body = file_get_contents('php://input');
            $this->json = json_decode($body, true) ?? [];
        }

        if ($key === null) {
            return $this->json;
        }

        return $this->json[$key] ?? $default;
    }

    /**
     * 获取任意输入（优先级：JSON > POST > GET）
     */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->json($key) ?? $this->post($key) ?? $this->query($key, $default);
    }

    /**
     * 获取请求头
     */
    public function header(string $name, mixed $default = null): mixed
    {
        $name = strtoupper(str_replace('-', '_', $name));
        return $this->headers[$name] ?? $default;
    }

    /**
     * 检查是否是指定方法
     */
    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    /**
     * 检查是否是 AJAX 请求
     */
    public function isAjax(): bool
    {
        return $this->header('X-REQUESTED-WITH') === 'XMLHttpRequest';
    }

    /**
     * 检查是否期望 JSON 响应
     */
    public function wantsJson(): bool
    {
        $accept = $this->header('ACCEPT', '');
        return str_contains($accept, 'application/json');
    }
}
