<?php
declare(strict_types=1);

namespace Services\AI;

/**
 * AI 系统配置加载器
 */
class Config
{
    private static ?array $config = null;

    /**
     * 加载配置
     */
    public static function load(string $envFile = '.env'): void
    {
        if (!file_exists($envFile)) {
            throw new \RuntimeException("配置文件不存在: {$envFile}");
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);

            // 跳过注释
            if (str_starts_with($line, '#')) {
                continue;
            }

            // 解析 KEY=VALUE
            if (strpos($line, '=') !== false) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
            }
        }

        self::$config = self::buildConfig();
    }

    /**
     * 构建配置数组
     */
    private static function buildConfig(): array
    {
        return [
            // CFC V7.7：通用中转 API（支持所有市场模型）
            'ai' => [
                'api_key' => self::env('AI_API_KEY', ''),
                'base_url' => self::env('AI_BASE_URL', 'https://tbnx.plus7.plus/v1'),
            ],
            'deepseek' => [
                'api_key' => self::env('DEEPSEEK_API_KEY', ''),
                'base_url' => self::env('DEEPSEEK_BASE_URL', 'https://tbnx.plus7.plus/v1'),
            ],
            'openai' => [
                'api_key' => self::env('OPENAI_API_KEY', ''),
                'base_url' => self::env('OPENAI_BASE_URL', 'https://www.chataiapi.com/v1'),
            ],
            'vision' => [
                'api_key' => self::env('VISION_API_KEY', self::env('OPENAI_API_KEY', '')),
                'base_url' => self::env('VISION_BASE_URL', 'https://api.mttieeo.com/v1'),
                'model' => self::env('VISION_MODEL', '[Y]gemini-2.5-pro'),
            ],
            'default_model' => self::env('DEFAULT_MODEL', 'deepseek-v3-250324'),
            'vision_model' => self::env('VISION_MODEL', '[Y]gemini-2.5-pro'),
            'rate_limit' => [
                'per_minute' => (int) self::env('RATE_LIMIT_PER_MINUTE', '10'),
                'per_hour' => (int) self::env('RATE_LIMIT_PER_HOUR', '100'),
            ],
            'safety' => [
                'max_input_length' => (int) self::env('MAX_INPUT_LENGTH', '10000'),
            ],
            'tools' => [
                'max_iterations' => (int) self::env('MAX_TOOL_ITERATIONS', '5'),
                'timeout' => (int) self::env('TOOL_EXECUTION_TIMEOUT', '5'),
            ],
            'memory' => [
                'max_short_term_messages' => (int) self::env('MAX_SHORT_TERM_MESSAGES', '20'),
                'ttl' => (int) self::env('MEMORY_TTL', '3600'),
            ],
            'queue' => [
                'max_retries' => (int) self::env('QUEUE_MAX_RETRIES', '3'),
                'retry_delay' => (int) self::env('QUEUE_RETRY_DELAY', '60'),
            ],
            'output_format' => self::env('OUTPUT_FORMAT', 'json'),
            'embedding' => [
                'model' => self::env('EMBEDDING_MODEL', 'text-embedding-3-small'),
                'dimensions' => (int) self::env('EMBEDDING_DIMENSIONS', '1536'),
                'chunk_size' => (int) self::env('CHUNK_SIZE', '512'),
                'chunk_overlap' => (int) self::env('CHUNK_OVERLAP', '50'),
            ],
            'logging' => [
                'level' => self::env('LOG_LEVEL', 'info'),
                'detail' => self::env('LOG_DETAIL', 'false') === 'true',
            ],
            // CFC V7.7：PostgreSQL 向量库配置
            'pgsql' => [
                'host' => self::env('PG_HOST', '127.0.0.1'),
                'port' => self::env('PG_PORT', '5432'),
                'database' => self::env('PG_DATABASE', 'cy_cfc_pg'),
                'username' => self::env('PG_USERNAME', 'cy_cfc_pg'),
                'password' => self::env('PG_PASSWORD', '123456'),
            ],
        ];
    }

    /**
     * 获取环境变量
     */
    private static function env(string $key, string $default = ''): string
    {
        return $_ENV[$key] ?? getenv($key) ?: $default;
    }

    /**
     * 获取配置值
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (self::$config === null) {
            self::load();
        }

        $keys = explode('.', $key);
        $value = self::$config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * 获取所有配置
     */
    public static function all(): array
    {
        if (self::$config === null) {
            self::load();
        }

        return self::$config;
    }
}
