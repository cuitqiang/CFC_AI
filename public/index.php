<?php
/**
 * CFC V7.7 Unified Entry Point (统一入口)
 * 
 * 架构: Request -> Router -> Controller -> Response
 * 
 * 所有 API 请求都通过此入口
 */

declare(strict_types=1);

// 1. 定义根目录
define('APP_ROOT', dirname(__DIR__));

// 2. 加载 Composer 自动加载
require_once APP_ROOT . '/vendor/autoload.php';

// 3. 引入核心类
use App\Bootstrap\App;
use App\Core\Request;
use App\Core\Response;

try {
    // 4. 框架启动 (加载 .env, 初始化路由)
    $app = App::boot(APP_ROOT);

    // 5. 捕获请求
    $request = Request::capture();

    // 6. 路由分发
    $response = $app->router->dispatch($request);

    // 7. 发送响应
    $response->send();

} catch (Throwable $e) {
    // 全局异常兜底
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => defined('APP_DEBUG') && APP_DEBUG ? $e->getTrace() : [],
    ], JSON_UNESCAPED_UNICODE);
}
