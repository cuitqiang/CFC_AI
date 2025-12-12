<?php
declare(strict_types=1);

namespace App\Bootstrap;

use App\Core\Request;
use App\Core\Response;

/**
 * RESTful 路由器
 * 
 * CFC V7.7 规范：
 * - 支持 RESTful URI 风格 (/api/debate/stream)
 * - 支持路由参数 (/api/user/{id})
 * - 支持中间件（预留）
 */
class Router
{
    private array $routes = [];
    private array $middlewares = [];

    /**
     * 注册 GET 路由
     */
    public function get(string $path, array|callable $handler): self
    {
        return $this->addRoute('GET', $path, $handler);
    }

    /**
     * 注册 POST 路由
     */
    public function post(string $path, array|callable $handler): self
    {
        return $this->addRoute('POST', $path, $handler);
    }

    /**
     * 注册 PUT 路由
     */
    public function put(string $path, array|callable $handler): self
    {
        return $this->addRoute('PUT', $path, $handler);
    }

    /**
     * 注册 DELETE 路由
     */
    public function delete(string $path, array|callable $handler): self
    {
        return $this->addRoute('DELETE', $path, $handler);
    }

    /**
     * 注册任意方法路由
     */
    public function any(string $path, array|callable $handler): self
    {
        return $this->addRoute('ANY', $path, $handler);
    }

    /**
     * 添加路由
     */
    private function addRoute(string $method, string $path, array|callable $handler): self
    {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'pattern' => $this->buildPattern($path),
            'handler' => $handler,
        ];
        return $this;
    }

    /**
     * 构建正则匹配模式
     */
    private function buildPattern(string $path): string
    {
        // 将 {param} 转换为命名捕获组
        $pattern = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }

    /**
     * 分发请求
     */
    public function dispatch(Request $request): Response
    {
        $method = $request->method();
        $path = $request->path();

        foreach ($this->routes as $route) {
            // 检查方法匹配
            if ($route['method'] !== 'ANY' && $route['method'] !== $method) {
                continue;
            }

            // 检查路径匹配
            if (preg_match($route['pattern'], $path, $matches)) {
                // 提取路由参数
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                
                return $this->callHandler($route['handler'], $request, $params);
            }
        }

        // 404 Not Found
        return Response::notFound("Route not found: [{$method}] {$path}");
    }

    /**
     * 调用处理器
     * 
     * CFC V7.7 增强：支持依赖注入
     * - Case 1: 闭包函数 callable
     * - Case 2: [Controller实例, 'method'] - 依赖注入模式（推荐）
     * - Case 3: [Controller::class, 'method'] - 仅限无依赖的简单控制器
     */
    private function callHandler(array|callable $handler, Request $request, array $params): Response
    {
        try {
            // Case 1: 闭包函数
            if (is_callable($handler) && !is_array($handler)) {
                $result = $handler($request, $params);
            }
            // Case 2: [Controller实例, 'method'] - CFC V7.7 依赖注入模式
            elseif (is_array($handler) && count($handler) === 2 && is_object($handler[0])) {
                [$controllerInstance, $method] = $handler;
                
                if (!method_exists($controllerInstance, $method)) {
                    return Response::error("Method not found: {$method}", 500);
                }
                
                $result = $controllerInstance->$method($request, $params);
            }
            // Case 3: [Controller::class, 'method'] - 仅限无依赖的简单控制器
            elseif (is_array($handler) && count($handler) === 2 && is_string($handler[0])) {
                [$controllerClass, $method] = $handler;

                if (!class_exists($controllerClass)) {
                    return Response::error("Controller not found: {$controllerClass}", 500);
                }

                // ⚠️ 警告：这种方式不支持依赖注入，仅用于简单控制器
                $controller = new $controllerClass();

                if (!method_exists($controller, $method)) {
                    return Response::error("Method not found: {$method}", 500);
                }

                $result = $controller->$method($request, $params);
            } 
            else {
                return Response::error('Invalid route handler', 500);
            }

            // 如果返回的已经是 Response 对象，直接返回
            if ($result instanceof Response) {
                return $result;
            }

            // 如果返回数组，转为 JSON 响应
            if (is_array($result)) {
                return Response::json($result);
            }

            // 其他情况返回空响应（如 SSE）
            return Response::sse();

        } catch (\Throwable $e) {
            return Response::error($e->getMessage(), 500);
        }
    }

    /**
     * 加载路由配置文件
     */
    public function loadRoutes(string $file): self
    {
        if (file_exists($file)) {
            $router = $this;
            require $file;
        }
        return $this;
    }

    /**
     * 获取所有路由（调试用）
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}
