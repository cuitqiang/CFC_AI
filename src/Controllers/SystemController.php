<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Bootstrap\App;

/**
 * 系统控制器
 * 
 * CFC V7.7 规范：
 * - 职责：系统级接口（健康检查、配置等）
 */
class SystemController
{
    /**
     * 健康检查接口
     * 
     * @route GET /api/health
     */
    public function health(Request $request, array $params): Response
    {
        return Response::success([
            'status' => 'healthy',
            'version' => '7.7.0',
            'framework' => 'CFC V7.7',
            'timestamp' => date('c'),
            'php_version' => PHP_VERSION,
            'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
        ], 'System is running');
    }

    /**
     * 系统信息接口
     * 
     * @route GET /api/system/info
     */
    public function info(Request $request, array $params): Response
    {
        return Response::success([
            'server' => php_uname('s'),
            'hostname' => gethostname(),
            'php_sapi' => PHP_SAPI,
            'extensions' => [
                'curl' => extension_loaded('curl'),
                'json' => extension_loaded('json'),
                'pdo' => extension_loaded('pdo'),
                'mbstring' => extension_loaded('mbstring'),
            ],
        ]);
    }
}
