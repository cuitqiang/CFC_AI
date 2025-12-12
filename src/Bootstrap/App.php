<?php
declare(strict_types=1);

namespace App\Bootstrap;

/**
 * CFC 框架启动器
 * 
 * CFC V7.7 规范：
 * - 加载配置、环境变量
 * - 初始化核心服务
 * - 提供全局访问接口
 */
class App
{
    private static ?self $instance = null;
    private static bool $booted = false;
    private static string $rootPath = '';

    public Router $router;

    private function __construct()
    {
        $this->router = new Router();
    }

    /**
     * 启动框架（单例）
     */
    public static function boot(string $rootPath): self
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        self::$rootPath = $rootPath;

        // 定义全局常量
        if (!defined('APP_ROOT')) {
            define('APP_ROOT', $rootPath);
        }

        if (!defined('APP_DEBUG')) {
            define('APP_DEBUG', self::env('APP_DEBUG', 'false') === 'true');
        }

        // 加载环境变量
        self::loadEnv();

        // 创建实例
        self::$instance = new self();

        // 加载路由配置
        self::$instance->router->loadRoutes($rootPath . '/src/Bootstrap/routes.php');

        self::$booted = true;

        return self::$instance;
    }

    /**
     * 加载 .env 文件
     */
    private static function loadEnv(): void
    {
        $envFile = self::$rootPath . '/.env';

        if (!file_exists($envFile)) {
            return;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);
            if (str_starts_with($line, '#') || empty($line)) {
                continue;
            }

            if (strpos($line, '=') !== false) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // 去除引号
                if (preg_match('/^(["\'])(.*)\\1$/', $value, $m)) {
                    $value = $m[2];
                }

                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
            }
        }
    }

    /**
     * 获取项目根路径
     */
    public static function getRootPath(): string
    {
        return self::$rootPath;
    }

    /**
     * 获取配置（从 src/Config/ 目录加载）
     */
    public static function config(string $key, mixed $default = null): mixed
    {
        static $configs = [];

        $parts = explode('.', $key);
        $file = array_shift($parts);

        if (!isset($configs[$file])) {
            $configFile = self::$rootPath . '/src/Config/' . $file . '.php';
            if (file_exists($configFile)) {
                $configs[$file] = require $configFile;
            } else {
                return $default;
            }
        }

        $config = $configs[$file];

        foreach ($parts as $part) {
            if (!is_array($config) || !isset($config[$part])) {
                return $default;
            }
            $config = $config[$part];
        }

        return $config;
    }

    /**
     * 获取环境变量
     */
    public static function env(string $key, mixed $default = null): mixed
    {
        return $_ENV[$key] ?? getenv($key) ?: $default;
    }

    /**
     * 获取应用实例
     */
    public static function getInstance(): ?self
    {
        return self::$instance;
    }

    /**
     * 是否已启动
     */
    public static function isBooted(): bool
    {
        return self::$booted;
    }
}
