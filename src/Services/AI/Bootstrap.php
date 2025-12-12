<?php
declare(strict_types=1);

/**
 * AI Agent 系统引导文件
 * 提供一键初始化整个系统
 */

namespace Services\AI;

use Services\AI\Config;
use Services\AI\Core\AIManager;
use Services\AI\Core\ModelRouter;
use Services\AI\Core\RAG\EmbeddingEngine;
use Services\AI\Providers\DeepseekProvider;
use Services\AI\Providers\OpenAIProvider;
use Services\AI\Providers\RelayProvider;
use Services\AI\Providers\EmbeddingProvider;
use Services\AI\Tools\ToolRegistry;
use Services\AI\Tools\System\TimeCalculator;
use Services\AI\Tools\System\DatabaseReader;
use Services\AI\Tools\System\HttpSearch;
use Services\AI\Tools\Business\ContractFinder;
use Services\AI\Tools\Business\EmailSender;
use Services\AI\Tools\Business\ReportBuilder;
use Services\AI\Memory\ContextManager;
use Services\AI\Memory\ShortTerm;
use Services\AI\Memory\Summary;
use Services\AI\Memory\VectorStore;
use Services\AI\Queue\AIJobDispatcher;
use Services\AI\Queue\AIJobWorker;
use Services\AI\Queue\DeadLetterQueue;
use Services\AI\Analytics\CostCalculator;
use Services\AI\Analytics\UsageTracker;

/**
 * AI Agent 系统引导类
 */
class Bootstrap
{
    private static ?AIManager $aiManager = null;
    private static ?ModelRouter $modelRouter = null;
    private static ?ToolRegistry $toolRegistry = null;
    private static ?ContextManager $contextManager = null;
    private static ?AIJobDispatcher $dispatcher = null;
    private static ?CostCalculator $costCalculator = null;
    private static ?UsageTracker $usageTracker = null;
    
    /** @var \PDO|null PostgreSQL 连接（向量库专用） */
    private static ?\PDO $pgPdo = null;
    
    /** @var \PDO|null MySQL 连接（业务数据） */
    private static ?\PDO $mysqlPdo = null;

    /**
     * 初始化整个系统
     */
    public static function initialize(string $envFile = ''): void
    {
        // 如果未指定 envFile，自动查找项目根目录
        if (empty($envFile)) {
            $envFile = self::findEnvFile();
        }

        // 加载配置
        Config::load($envFile);

        // 初始化各个组件
        self::initializeProviders();
        self::initializeTools();
        self::initializeMemory();
        self::initializeQueue();
        self::initializeAnalytics();
        self::initializeAIManager();
    }

    /**
     * 查找 .env 文件路径
     * 从当前目录向上查找，直到找到 .env 文件或 vendor 目录
     */
    private static function findEnvFile(): string
    {
        // 优先使用 APP_ROOT 常量（如果定义了）
        if (defined('APP_ROOT') && file_exists(APP_ROOT . '/.env')) {
            return APP_ROOT . '/.env';
        }

        // 从 Bootstrap.php 所在目录向上查找
        $dir = __DIR__;
        $maxDepth = 10;
        
        while ($maxDepth-- > 0) {
            $envPath = $dir . '/.env';
            if (file_exists($envPath)) {
                return $envPath;
            }
            
            // 如果找到 vendor 目录，说明已经到项目根目录
            if (file_exists($dir . '/vendor')) {
                // 但 .env 不存在，抛出异常
                break;
            }
            
            $parent = dirname($dir);
            if ($parent === $dir) {
                break; // 已到根目录
            }
            $dir = $parent;
        }

        throw new \RuntimeException(
            "找不到 .env 配置文件。请确保项目根目录存在 .env 文件，或使用 Bootstrap::initialize('/path/to/.env') 指定路径。"
        );
    }

    /**
     * 初始化 Providers
     */
    private static function initializeProviders(): void
    {
        self::$modelRouter = new ModelRouter();

        // 通用中转 Provider（优先级最高，支持所有模型）
        if (Config::get('ai.api_key')) {
            $relayProvider = new RelayProvider(
                Config::get('ai.api_key'),
                Config::get('ai.base_url')
            );
            self::$modelRouter->register('relay', $relayProvider);
        }

        // Deepseek Provider（备用）
        if (Config::get('deepseek.api_key')) {
            $deepseekProvider = new DeepseekProvider(
                Config::get('deepseek.api_key'),
                Config::get('deepseek.base_url')
            );
            self::$modelRouter->register('deepseek', $deepseekProvider);
        }

        // OpenAI Provider（备用）
        if (Config::get('openai.api_key')) {
            $openaiProvider = new OpenAIProvider(
                Config::get('openai.api_key'),
                Config::get('openai.base_url')
            );
            self::$modelRouter->register('openai', $openaiProvider);
        }

        // 设置默认 Provider 为中转
        self::$modelRouter->setDefaultProvider('relay');
    }

    /**
     * 初始化 Tools
     */
    private static function initializeTools(): void
    {
        self::$toolRegistry = new ToolRegistry();

        // 注册 System 工具
        self::$toolRegistry->register(new TimeCalculator());
        self::$toolRegistry->register(new DatabaseReader());
        self::$toolRegistry->register(new HttpSearch());

        // 注册 Business 工具
        self::$toolRegistry->register(new ContractFinder());
        self::$toolRegistry->register(new EmailSender());
        self::$toolRegistry->register(new ReportBuilder());
    }

    /**
     * 初始化 Memory
     */
    private static function initializeMemory(): void
    {
        $shortTerm = new ShortTerm(Config::get('memory.ttl'));
        $summary = new Summary();

        // 初始化 VectorStore（需要 EmbeddingProvider）
        $vectorStore = null;
        if (Config::get('openai.api_key')) {
            $embeddingProvider = new EmbeddingProvider(
                Config::get('openai.api_key'),
                Config::get('openai.base_url')
            );

            $embeddingEngine = new EmbeddingEngine(
                $embeddingProvider,
                Config::get('embedding.model'),
                Config::get('embedding.chunk_size'),
                Config::get('embedding.dimensions')
            );

            $vectorStore = new VectorStore($embeddingEngine);
        } else {
            $vectorStore = new VectorStore();
        }

        self::$contextManager = new ContextManager(
            $shortTerm,
            $summary,
            $vectorStore,
            Config::get('memory.max_short_term_messages')
        );
    }

    /**
     * 初始化 Queue
     */
    private static function initializeQueue(): void
    {
        self::$dispatcher = new AIJobDispatcher();
        self::$dispatcher->registerQueue('default', [
            'max_retries' => Config::get('queue.max_retries'),
            'retry_delay' => Config::get('queue.retry_delay'),
        ]);
        self::$dispatcher->registerQueue('high_priority', [
            'priority' => 10,
        ]);
    }

    /**
     * 初始化 Analytics
     */
    private static function initializeAnalytics(): void
    {
        self::$costCalculator = new CostCalculator();
        self::$usageTracker = new UsageTracker();
    }

    /**
     * 初始化 AIManager
     */
    private static function initializeAIManager(): void
    {
        self::$aiManager = new AIManager(
            self::$modelRouter,
            self::$toolRegistry,
            self::$contextManager,
            Config::all()
        );
    }

    /**
     * 获取 AIManager 实例
     */
    public static function getAIManager(): AIManager
    {
        if (self::$aiManager === null) {
            self::initialize();
        }

        return self::$aiManager;
    }

    /**
     * 获取 ModelRouter 实例
     */
    public static function getModelRouter(): ModelRouter
    {
        if (self::$modelRouter === null) {
            self::initialize();
        }

        return self::$modelRouter;
    }

    /**
     * 获取 ToolRegistry 实例
     */
    public static function getToolRegistry(): ToolRegistry
    {
        if (self::$toolRegistry === null) {
            self::initialize();
        }

        return self::$toolRegistry;
    }

    /**
     * 获取 ContextManager 实例
     */
    public static function getContextManager(): ContextManager
    {
        if (self::$contextManager === null) {
            self::initialize();
        }

        return self::$contextManager;
    }

    /**
     * 获取 AIJobDispatcher 实例
     */
    public static function getDispatcher(): AIJobDispatcher
    {
        if (self::$dispatcher === null) {
            self::initialize();
        }

        return self::$dispatcher;
    }

    /**
     * 获取 CostCalculator 实例
     */
    public static function getCostCalculator(): CostCalculator
    {
        if (self::$costCalculator === null) {
            self::initialize();
        }

        return self::$costCalculator;
    }

    /**
     * 获取 UsageTracker 实例
     */
    public static function getUsageTracker(): UsageTracker
    {
        if (self::$usageTracker === null) {
            self::initialize();
        }

        return self::$usageTracker;
    }

    /**
     * 快速创建一个配置好的 AIManager（便捷方法）
     */
    public static function createAIManager(?string $envFile = null): AIManager
    {
        if ($envFile !== null) {
            self::initialize($envFile);
        }

        return self::getAIManager();
    }
    
    /**
     * 获取 PostgreSQL PDO 实例（向量库专用）
     * 
     * CFC V7.7 规范：统一数据库连接管理
     * 所有需要 pgvector 的服务必须通过此方法获取连接
     * 
     * @return \PDO PostgreSQL PDO 实例
     */
    public static function getPgPdo(): \PDO
    {
        if (self::$pgPdo === null) {
            // 尝试从 Config 读取（如果已初始化）
            // 否则从环境变量读取（CFC V7.7：支持已加载的 App 环境）
            try {
                // 先尝试初始化（会加载 Config）
                if (defined('APP_ROOT') && file_exists(APP_ROOT . '/.env')) {
                    self::initialize(APP_ROOT . '/.env');
                }
                
                $host = Config::get('pgsql.host', null) ?? $_ENV['PG_HOST'] ?? '127.0.0.1';
                $port = Config::get('pgsql.port', null) ?? $_ENV['PG_PORT'] ?? '5432';
                $dbname = Config::get('pgsql.database', null) ?? $_ENV['PG_DATABASE'] ?? 'cy_cfc_pg';
                $user = Config::get('pgsql.username', null) ?? $_ENV['PG_USERNAME'] ?? 'cy_cfc_pg';
                $pass = Config::get('pgsql.password', null) ?? $_ENV['PG_PASSWORD'] ?? '123456';
            } catch (\Throwable $e) {
                // Config 未初始化，直接从环境变量读取
                $host = $_ENV['PG_HOST'] ?? '127.0.0.1';
                $port = $_ENV['PG_PORT'] ?? '5432';
                $dbname = $_ENV['PG_DATABASE'] ?? 'cy_cfc_pg';
                $user = $_ENV['PG_USERNAME'] ?? 'cy_cfc_pg';
                $pass = $_ENV['PG_PASSWORD'] ?? '123456';
            }
            
            self::$pgPdo = new \PDO(
                "pgsql:host={$host};port={$port};dbname={$dbname}",
                $user,
                $pass,
                [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                ]
            );
        }
        
        return self::$pgPdo;
    }

    /**
     * 获取 MySQL PDO 连接（单例）
     * 
     * CFC V7.7 规范：统一数据库连接管理
     * 业务数据使用 MySQL
     * 
     * @return \PDO MySQL PDO 实例
     */
    public static function getPDO(): \PDO
    {
        if (self::$mysqlPdo === null) {
            try {
                if (defined('APP_ROOT') && file_exists(APP_ROOT . '/.env')) {
                    self::initialize(APP_ROOT . '/.env');
                }
                
                $host = Config::get('mysql.host', null) ?? $_ENV['DB_HOST'] ?? '127.0.0.1';
                $port = Config::get('mysql.port', null) ?? $_ENV['DB_PORT'] ?? '3306';
                $dbname = Config::get('mysql.database', null) ?? $_ENV['DB_DATABASE'] ?? 'cy_cfc';
                $user = Config::get('mysql.username', null) ?? $_ENV['DB_USERNAME'] ?? 'cy_cfc';
                $pass = Config::get('mysql.password', null) ?? $_ENV['DB_PASSWORD'] ?? '123456';
            } catch (\Throwable $e) {
                $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
                $port = $_ENV['DB_PORT'] ?? '3306';
                $dbname = $_ENV['DB_DATABASE'] ?? 'cy_cfc';
                $user = $_ENV['DB_USERNAME'] ?? 'cy_cfc';
                $pass = $_ENV['DB_PASSWORD'] ?? '123456';
            }
            
            self::$mysqlPdo = new \PDO(
                "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4",
                $user,
                $pass,
                [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        }
        
        return self::$mysqlPdo;
    }
}
