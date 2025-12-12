# CRM_ERP_V7.6 AI Agent System

> **版本**: V7.6 Enterprise
> **更新日期**: 2025-12-11
> **架构级别**: 企业级 AI Agent 系统（完整实现）

---

## 📋 目录

1. [架构概览](#架构概览)
2. [目录结构](#目录结构)
3. [各层详细设计](#各层详细设计)
4. [核心组件实现](#核心组件实现)
5. [使用示例](#使用示例)
6. [数据库设计](#数据库设计)
7. [开发规范](#开发规范)

---

## 架构概览

### 设计理念

本 AI 模块采用**分层架构**设计，具备以下特点：

| 特性 | 说明 |
|------|------|
| **多模型支持** | Deepseek、OpenAI、Ollama、Qwen 等模型自由切换 |
| **记忆系统** | 三级记忆体系（短期/摘要/长期） |
| **工具调用** | Function Calling 支持，AI 可执行实际操作 |
| **流水线** | 可插拔的 Pipeline 处理流程 |
| **成本控制** | 完整的用量统计和告警机制 |
| **异步队列** | 支持耗时任务后台执行 |

### 请求处理流程

```
┌─────────────────────────────────────────────────────────────────────────┐
│                           用户请求                                       │
└──────────────────────────────┬──────────────────────────────────────────┘
                               ▼
┌─────────────────────────────────────────────────────────────────────────┐
│  AIManager.php (统一门面)                                                │
│  └── 选择同步执行 or 异步队列                                             │
└──────────────────────────────┬──────────────────────────────────────────┘
                               ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                         Pipeline 流水线                                  │
├─────────┬──────────┬──────────┬──────────┬──────────┬──────────┬───────┤
│ 0_限流  │ 1_安全   │ 2_记忆   │ 3_工具   │ 4_模型   │ 5_执行   │ 6_保存│
│ 检查    │ 检查     │ 加载     │ 规划     │ 调用     │ 工具     │ 记忆  │
└─────────┴──────────┴──────────┴──────────┴──────────┴──────────┴───────┘
                               ▼
┌─────────────────────────────────────────────────────────────────────────┐
│  Provider (Deepseek/OpenAI/Ollama)                                      │
└──────────────────────────────┬──────────────────────────────────────────┘
                               ▼
┌─────────────────────────────────────────────────────────────────────────┐
│  返回结果 (JSON/Stream)                                                  │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 目录结构

```
src/Services/AI/
│
├── Core/                           # 🧱 神经中枢
│   ├── AIManager.php               # 👑 统一门面 (Facade)
│   ├── ModelRouter.php             # 🧠 模型路由 (根据任务复杂度自动切模型)
│   ├── RAG/                        # 📚 RAG 引擎
│   │   ├── EmbeddingEngine.php     # 向量生成器 (调 API)
│   │   ├── DocumentChunker.php     # 文档切片器 (PDF/Word → 文本块)
│   │   └── FileHasher.php          # 文件哈希器 (MD5 去重)
│   └── Utils/
│       ├── FunctionCaller.php      # 工具调用解析器
│       ├── StreamHandler.php       # 流式响应处理 (SSE)
│       └── TokenCounter.php        # Token 计费辅助
│
├── Pipeline/                       # 🔄 流水线 (洋葱模型)
│   ├── Pipeline.php                # 管道执行器
│   ├── PipelineContext.php         # 📦 DTO 数据包 (贯穿全流程)
│   └── Pipes/
│       ├── 0_RateLimit.php         # 限流检查 (Redis 漏桶)
│       ├── 1_SafetyCheck.php       # 内容安全 / Prompt 注入检测
│       ├── 2_LoadMemory.php        # 加载历史上下文 & RAG 知识库
│       ├── 3_PlanTools.php         # 思考需要用什么工具
│       ├── 4_CallModel.php         # 请求大模型 (含降级重试)
│       ├── 5_ExecuteTool.php       # 执行工具 (查库/发信)
│       ├── 6_SaveMemory.php        # 结果回写
│       └── 7_FormatOutput.php      # 输出格式化 (JSON/Markdown 清洗)
│
├── Tools/                          # 🛠️ 工具箱 (AI 的手脚)
│   ├── BaseTool.php                # 抽象基类
│   ├── ToolRegistry.php            # 注册中心
│   ├── ToolSandbox.php             # 安全沙箱 (权限控制)
│   ├── System/
│   │   ├── DatabaseReader.php      # 🛡️ 只读 SQL 查询器
│   │   ├── HttpSearch.php          # 🌐 联网搜索 (SerpApi)
│   │   └── TimeCalculator.php      # 时间计算
│   └── Business/
│       ├── ContractFinder.php      # 合同查询
│       ├── EmailSender.php         # 发送邮件
│       └── ReportBuilder.php       # 生成报表数据
│
├── Providers/                      # 🔌 驱动层 (适配不同厂商)
│   ├── ProviderInterface.php       # 接口契约
│   ├── AbstractProvider.php        # 公共逻辑 (Log, Http, Retry)
│   ├── DeepseekProvider.php        # 高性价比主力
│   ├── OpenAIProvider.php          # 复杂逻辑兜底
│   ├── OllamaProvider.php          # 敏感数据本地跑
│   └── EmbeddingProvider.php       # 专门用于生成向量
│
├── Memory/                         # 💾 存储层 (只负责存取)
│   ├── ContextManager.php          # 对话组装逻辑
│   ├── ShortTerm.php               # Redis (当前会话, TTL 24h)
│   ├── Summary.php                 # MySQL (历史摘要)
│   └── VectorStore.php             # 向量数据库 (Milvus/PgVector)
│
├── Tasks/                          # 📋 指令层 (预设场景)
│   ├── BaseTask.php                # 任务基类
│   ├── GeneralAgent.php            # 通用助手 (自由调用工具)
│   ├── ContractReview.php          # 合同审查 (强制挂载 RAG)
│   ├── WorktimeEstimate.php        # 工时估算 (强制挂载历史数据)
│   └── DataAnalyst.php             # 数据分析 (强制挂载 DatabaseReader)
│
├── Prompts/                        # 💬 提示词资源
│   ├── TemplateManager.php         # 模板管理 & 版本控制
│   └── templates/
│       ├── system_persona.md       # 系统人设
│       ├── worktime_v1.md          # 工时估算
│       ├── contract_risk.md        # 合同风险
│       └── data_analyst.md         # 数据分析
│
├── Queue/                          # ⚡ 异步队列层
│   ├── AIJobDispatcher.php         # 📤 任务分发器 (入口)
│   ├── AIJobWorker.php             # 🔄 队列消费者 (CLI 常驻进程)
│   ├── JobStatus.php               # 📊 任务状态枚举
│   ├── PriorityScheduler.php       # ⚖️ 优先级调度
│   ├── DeadLetterQueue.php         # 💀 死信队列 (失败归档)
│   │
│   └── Jobs/                       # 📋 具体任务定义
│       ├── BaseJob.php             # 任务基类
│       ├── RunAgentJob.php         # 🤖 执行 Agent 任务 (通用)
│       ├── VectorizeDocJob.php     # 📄 文档向量化 (含哈希去重)
│       ├── ContractReviewJob.php   # 📝 合同审查 (耗时长)
│       ├── BatchEstimateJob.php    # ⏱️ 批量工时估算
│       ├── ReportGenerateJob.php   # 📊 报表生成
│       └── SyncKnowledgeJob.php    # 📚 知识库同步
│
└── Analytics/                      # 📊 监控层
    ├── CostCalculator.php          # 💰 计费引擎
    ├── UsageTracker.php            # 📈 用量统计
    ├── AlertService.php            # 🚨 告警服务 (Token 暴涨/失败率高)
    └── Dashboard/
        ├── DailyStats.php          # 每日统计
        └── ProviderComparison.php  # 模型对比
```

---

## 各层详细设计

### 1. Core 层 - 神经中枢

#### AIManager.php - 统一门面

外部代码**只需要调用这一个类**，所有复杂逻辑都被封装在内部。

```php
<?php
namespace Services\AI\Core;

class AIManager
{
    /**
     * 同步执行任务
     */
    public static function run(string $taskName, array $input): array
    {
        $task = TaskFactory::create($taskName);
        $pipeline = self::buildPipeline($task);
        $context = new PipelineContext($taskName, $input);
        
        return $pipeline->process($context);
    }
    
    /**
     * 流式输出
     */
    public static function stream(string $taskName, array $input, callable $onChunk): void
    {
        // 实现流式响应逻辑
    }
    
    /**
     * Agent 模式（带工具调用）
     */
    public static function agent(string $agentName): AgentBuilder
    {
        return new AgentBuilder($agentName);
    }
    
    /**
     * 异步执行
     */
    public static function async(string $taskName, array $input): AsyncBuilder
    {
        return new AsyncBuilder($taskName, $input);
    }
}
```

#### ModelRouter.php - 模型路由

根据任务类型和复杂度自动选择最优模型。

```php
<?php
namespace Services\AI\Core;

class ModelRouter
{
    // 任务-模型映射
    private static array $taskModelMap = [
        'chat_assistant'    => 'deepseek',      // 简单对话用便宜的
        'worktime_estimate' => 'deepseek',      // 工时估算
        'contract_review'   => 'openai',        // 合同审查需要强模型
        'data_analysis'     => 'deepseek',      // 数据分析
        'code_review'       => 'openai',        // 代码审查
    ];
    
    // Token 阈值自动升级
    private static int $upgradeThreshold = 4000;
    
    public static function selectProvider(string $taskType, int $estimatedTokens): string
    {
        $provider = self::$taskModelMap[$taskType] ?? 'deepseek';
        
        // 如果预估 Token 很多，升级到更强的模型
        if ($estimatedTokens > self::$upgradeThreshold && $provider === 'deepseek') {
            return 'openai';
        }
        
        return $provider;
    }
}
```

#### RAG/FileHasher.php - 文件哈希去重

**省钱关键组件**！避免重复向量化相同文件。

```php
<?php
namespace Services\AI\Core\RAG;

use Core\DB;

class FileHasher
{
    /**
     * 计算文件哈希
     */
    public static function hash(string $filePath): string
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("文件不存在: $filePath");
        }
        
        $md5 = md5_file($filePath);
        $size = filesize($filePath);
        
        return "{$md5}_{$size}";
    }
    
    /**
     * 检查文件是否已向量化
     */
    public static function findByHash(string $hash): ?array
    {
        $pdo = DB::get();
        $stmt = $pdo->prepare("
            SELECT id, file_hash, file_path, doc_type, chunk_count, vectorized_at
            FROM vectorized_documents
            WHERE file_hash = ?
            LIMIT 1
        ");
        $stmt->execute([$hash]);
        
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    
    /**
     * 记录已向量化的文件
     */
    public static function recordVectorized(
        string $hash,
        string $filePath,
        string $docType,
        int $chunkCount,
        ?int $relatedId = null
    ): int {
        $pdo = DB::get();
        $stmt = $pdo->prepare("
            INSERT INTO vectorized_documents 
            (file_hash, file_path, doc_type, related_id, chunk_count, vectorized_at, created_at)
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([$hash, $filePath, $docType, $relatedId, $chunkCount]);
        
        return (int) $pdo->lastInsertId();
    }
    
    /**
     * 添加文件关联（去重时使用）
     */
    public static function addRelation(int $documentId, string $relationType, int $relatedId): void
    {
        $pdo = DB::get();
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO vectorized_document_relations
            (document_id, relation_type, related_id, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$documentId, $relationType, $relatedId]);
    }
}
```

---

### 2. Pipeline 层 - 流水线

#### PipelineContext.php - 数据传输对象

```php
<?php
namespace Services\AI\Pipeline;

class PipelineContext
{
    public string $taskType;
    public array $input;
    public ?string $userId = null;
    
    // 流水线中间数据
    public array $messages = [];
    public array $tools = [];
    public ?string $prompt = null;
    public mixed $rawResponse = null;
    public mixed $finalResult = null;
    public array $toolCalls = [];
    
    // 元数据
    public array $meta = [
        'start_time' => null,
        'end_time' => null,
        'total_tokens' => 0,
        'cost' => 0,
        'provider' => null,
    ];
    
    public function __construct(string $taskType, array $input, ?string $userId = null)
    {
        $this->taskType = $taskType;
        $this->input = $input;
        $this->userId = $userId;
        $this->meta['start_time'] = microtime(true);
    }
    
    public function addMessage(string $role, string $content): self
    {
        $this->messages[] = ['role' => $role, 'content' => $content];
        return $this;
    }
    
    public function setMeta(string $key, mixed $value): self
    {
        $this->meta[$key] = $value;
        return $this;
    }
    
    public function getDuration(): float
    {
        $end = $this->meta['end_time'] ?? microtime(true);
        return round($end - $this->meta['start_time'], 3);
    }
}
```

#### Pipeline.php - 管道执行器

```php
<?php
namespace Services\AI\Pipeline;

class Pipeline
{
    private array $pipes = [];
    
    public function pipe(callable $pipe): self
    {
        $this->pipes[] = $pipe;
        return $this;
    }
    
    public function process(PipelineContext $context): mixed
    {
        $pipeline = array_reduce(
            array_reverse($this->pipes),
            fn($next, $pipe) => fn($ctx) => $pipe($ctx, $next),
            fn($ctx) => $ctx->finalResult
        );
        
        return $pipeline($context);
    }
}
```

#### Pipes/0_RateLimit.php - 限流检查

```php
<?php
namespace Services\AI\Pipeline\Pipes;

use Services\AI\Pipeline\PipelineContext;

class RateLimitPipe
{
    private int $maxRequestsPerMinute = 20;
    
    public function __invoke(PipelineContext $ctx, callable $next)
    {
        $key = "ai_ratelimit:{$ctx->userId}";
        
        // 使用 Redis 进行限流
        $redis = new \Redis();
        $redis->connect('127.0.0.1', 6379);
        
        $count = $redis->incr($key);
        if ($count === 1) {
            $redis->expire($key, 60);
        }
        
        if ($count > $this->maxRequestsPerMinute) {
            throw new \RuntimeException('请求太频繁，请稍后再试');
        }
        
        return $next($ctx);
    }
}
```

#### Pipes/1_SafetyCheck.php - 安全检查

```php
<?php
namespace Services\AI\Pipeline\Pipes;

use Services\AI\Pipeline\PipelineContext;

class SafetyCheckPipe
{
    private array $forbiddenPatterns = [
        '/ignore\s+previous\s+instructions/i',
        '/system\s*:\s*/i',
        '/\<\|.*\|\>/i',
    ];
    
    public function __invoke(PipelineContext $ctx, callable $next)
    {
        $input = json_encode($ctx->input);
        
        foreach ($this->forbiddenPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                throw new \RuntimeException('检测到不安全的输入内容');
            }
        }
        
        // 检查输入长度
        if (strlen($input) > 50000) {
            throw new \RuntimeException('输入内容过长');
        }
        
        return $next($ctx);
    }
}
```

---

### 3. Tools 层 - 工具箱

#### BaseTool.php - 工具基类

```php
<?php
namespace Services\AI\Tools;

abstract class BaseTool
{
    // 工具元信息（给 LLM 看的）
    abstract public function getName(): string;
    abstract public function getDescription(): string;
    abstract public function getParameters(): array;
    
    // 执行逻辑
    abstract public function execute(array $params): mixed;
    
    // 安全控制
    public function getRequiredLevel(): int
    {
        return ToolSandbox::LEVEL_READONLY;
    }
    
    public function getRateLimit(): int
    {
        return 10; // 每分钟最多调用次数
    }
    
    /**
     * 转换为 OpenAI Function Calling 格式
     */
    public function toFunctionSchema(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->getName(),
                'description' => $this->getDescription(),
                'parameters' => $this->getParameters(),
            ]
        ];
    }
}
```

#### ToolSandbox.php - 安全沙箱

```php
<?php
namespace Services\AI\Tools;

class ToolSandbox
{
    const LEVEL_READONLY = 1;   // 只读（查询）
    const LEVEL_WRITE = 2;      // 可写（发邮件、创建任务）
    const LEVEL_DANGEROUS = 3;  // 危险（删除、修改数据库）
    
    private static array $rolePermissions = [
        'guest'  => self::LEVEL_READONLY,
        'member' => self::LEVEL_READONLY,
        'pm'     => self::LEVEL_WRITE,
        'admin'  => self::LEVEL_DANGEROUS,
    ];
    
    public static function canExecute(string $userRole, BaseTool $tool): bool
    {
        $requiredLevel = $tool->getRequiredLevel();
        $userLevel = self::$rolePermissions[$userRole] ?? self::LEVEL_READONLY;
        
        return $userLevel >= $requiredLevel;
    }
}
```

#### System/DatabaseReader.php - 只读 SQL 查询器

```php
<?php
namespace Services\AI\Tools\System;

use Services\AI\Tools\BaseTool;
use Services\AI\Tools\ToolSandbox;
use Core\DB;

class DatabaseReader extends BaseTool
{
    private array $allowedTables = [
        'projects', 'contracts', 'requirements',
        'tasks', 'customers', 'budgets'
    ];
    
    private array $forbiddenKeywords = [
        'INSERT', 'UPDATE', 'DELETE', 'DROP', 'TRUNCATE',
        'ALTER', 'CREATE', 'GRANT', 'REVOKE'
    ];
    
    public function getName(): string
    {
        return 'database_query';
    }
    
    public function getDescription(): string
    {
        return '执行只读 SQL 查询，获取项目、合同、任务等业务数据';
    }
    
    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'SQL 查询语句（只允许 SELECT）'
                ]
            ],
            'required' => ['query']
        ];
    }
    
    public function execute(array $params): mixed
    {
        $sql = $params['query'];
        
        // 安全检查：只允许 SELECT
        if (!preg_match('/^\s*SELECT/i', $sql)) {
            throw new \RuntimeException('只允许 SELECT 查询');
        }
        
        // 检查禁止关键词
        foreach ($this->forbiddenKeywords as $keyword) {
            if (stripos($sql, $keyword) !== false) {
                throw new \RuntimeException("禁止使用 $keyword");
            }
        }
        
        // 限制返回行数
        if (stripos($sql, 'LIMIT') === false) {
            $sql .= ' LIMIT 100';
        }
        
        $pdo = DB::get();
        return $pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getRequiredLevel(): int
    {
        return ToolSandbox::LEVEL_READONLY;
    }
}
```

---

### 4. Providers 层 - 模型驱动

#### ProviderInterface.php - 接口契约

```php
<?php
namespace Services\AI\Providers;

interface ProviderInterface
{
    /**
     * 普通对话
     */
    public function chat(array $messages, array $options = []): array;
    
    /**
     * 流式对话
     */
    public function chatStream(array $messages, callable $onChunk, array $options = []): void;
    
    /**
     * 带工具的对话
     */
    public function chatWithTools(array $messages, array $tools, array $options = []): array;
    
    /**
     * 获取模型名称
     */
    public function getName(): string;
    
    /**
     * 获取最大 Token 数
     */
    public function getMaxTokens(): int;
    
    /**
     * 获取每 1K Token 价格
     */
    public function getCostPer1KTokens(): float;
    
    /**
     * 健康检查
     */
    public function isAvailable(): bool;
}
```

#### AbstractProvider.php - 公共逻辑基类

```php
<?php
namespace Services\AI\Providers;

abstract class AbstractProvider implements ProviderInterface
{
    protected string $apiKey;
    protected string $baseUrl;
    protected int $timeout = 60;
    protected int $maxRetries = 3;
    
    protected function request(string $endpoint, array $data): array
    {
        $ch = curl_init($this->baseUrl . $endpoint);
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_TIMEOUT => $this->timeout,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new \RuntimeException("API 请求失败: HTTP $httpCode");
        }
        
        return json_decode($response, true);
    }
    
    protected function log(string $message, string $level = 'info'): void
    {
        $time = date('Y-m-d H:i:s');
        $provider = $this->getName();
        error_log("[$time][$level][$provider] $message");
    }
}
```

#### DeepseekProvider.php - Deepseek 实现

```php
<?php
namespace Services\AI\Providers;

class DeepseekProvider extends AbstractProvider
{
    public function __construct()
    {
        $this->apiKey = $_ENV['DEEPSEEK_API_KEY'] ?? '';
        $this->baseUrl = 'https://api.deepseek.com/v1';
    }
    
    public function chat(array $messages, array $options = []): array
    {
        $data = [
            'model' => $options['model'] ?? 'deepseek-chat',
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => $options['max_tokens'] ?? 2000,
        ];
        
        $response = $this->request('/chat/completions', $data);
        
        return [
            'content' => $response['choices'][0]['message']['content'] ?? '',
            'usage' => $response['usage'] ?? [],
            'model' => $response['model'] ?? 'deepseek-chat',
        ];
    }
    
    public function chatStream(array $messages, callable $onChunk, array $options = []): void
    {
        // 流式输出实现
    }
    
    public function chatWithTools(array $messages, array $tools, array $options = []): array
    {
        $data = [
            'model' => $options['model'] ?? 'deepseek-chat',
            'messages' => $messages,
            'tools' => $tools,
            'tool_choice' => 'auto',
        ];
        
        return $this->request('/chat/completions', $data);
    }
    
    public function getName(): string { return 'deepseek'; }
    public function getMaxTokens(): int { return 32000; }
    public function getCostPer1KTokens(): float { return 0.001; }
    public function isAvailable(): bool { return !empty($this->apiKey); }
}
```

---

### 5. Memory 层 - 记忆系统

#### ContextManager.php - 对话组装

```php
<?php
namespace Services\AI\Memory;

class ContextManager
{
    private ShortTerm $shortTerm;
    private Summary $summary;
    private VectorStore $vectorStore;
    
    public function __construct()
    {
        $this->shortTerm = new ShortTerm();
        $this->summary = new Summary();
        $this->vectorStore = new VectorStore();
    }
    
    /**
     * 构建完整上下文
     */
    public function buildContext(string $userId, string $query, array $options = []): array
    {
        $messages = [];
        
        // 1. 系统人设
        $messages[] = [
            'role' => 'system',
            'content' => $this->getSystemPersona($options['persona'] ?? 'default')
        ];
        
        // 2. RAG 知识库检索
        if ($options['enable_rag'] ?? false) {
            $relevantDocs = $this->vectorStore->search($query, [
                'limit' => $options['rag_limit'] ?? 3
            ]);
            
            if ($relevantDocs) {
                $messages[] = [
                    'role' => 'system',
                    'content' => "相关背景知识：\n" . implode("\n---\n", array_column($relevantDocs, 'content'))
                ];
            }
        }
        
        // 3. 历史摘要
        $historySummary = $this->summary->get($userId);
        if ($historySummary) {
            $messages[] = [
                'role' => 'system',
                'content' => "之前对话摘要：$historySummary"
            ];
        }
        
        // 4. 短期记忆（最近对话）
        $recentMessages = $this->shortTerm->get($userId, $options['history_limit'] ?? 10);
        $messages = array_merge($messages, $recentMessages);
        
        // 5. 当前问题
        $messages[] = ['role' => 'user', 'content' => $query];
        
        return $messages;
    }
    
    /**
     * 保存对话记录
     */
    public function saveConversation(string $userId, string $query, string $response): void
    {
        $this->shortTerm->append($userId, [
            ['role' => 'user', 'content' => $query],
            ['role' => 'assistant', 'content' => $response],
        ]);
    }
    
    private function getSystemPersona(string $type): string
    {
        $personas = [
            'default' => '你是一个专业的 CRM/ERP 系统助手...',
            'analyst' => '你是一个数据分析专家...',
            'reviewer' => '你是一个合同审查专家...',
        ];
        
        return $personas[$type] ?? $personas['default'];
    }
}
```

---

### 6. Queue 层 - 异步队列

#### Jobs/VectorizeDocJob.php - 文档向量化（含哈希去重）

```php
<?php
namespace Services\AI\Queue\Jobs;

use Services\AI\Core\RAG\DocumentChunker;
use Services\AI\Core\RAG\EmbeddingEngine;
use Services\AI\Core\RAG\FileHasher;
use Services\AI\Memory\VectorStore;

class VectorizeDocJob extends BaseJob
{
    protected string $type = 'vectorize_doc';
    protected int $priority = 3;
    protected int $timeout = 600;
    protected int $maxRetries = 2;
    
    public function __construct(
        string $filePath,
        string $docType,
        ?int $relatedId = null,
        ?string $userId = null
    ) {
        $this->payload = [
            'file_path' => $filePath,
            'doc_type' => $docType,
            'related_id' => $relatedId,
        ];
        $this->userId = $userId;
    }
    
    public function handle(): mixed
    {
        $filePath = $this->payload['file_path'];
        $docType = $this->payload['doc_type'];
        $relatedId = $this->payload['related_id'];
        
        // ========== 🔑 哈希去重检查 ==========
        $fileHash = FileHasher::hash($filePath);
        $existing = FileHasher::findByHash($fileHash);
        
        if ($existing) {
            // 文件已存在，直接关联，省钱！
            if ($relatedId) {
                FileHasher::addRelation($existing['id'], $docType, $relatedId);
            }
            
            return [
                'status' => 'skipped',
                'reason' => 'duplicate_file',
                'existing_document_id' => $existing['id'],
                'cost_saved' => true,
            ];
        }
        
        // ========== 切片 ==========
        $chunker = new DocumentChunker();
        $chunks = $chunker->chunk($filePath, [
            'chunk_size' => 500,
            'chunk_overlap' => 50,
        ]);
        
        // ========== 向量化并存储 ==========
        $engine = new EmbeddingEngine();
        $store = new VectorStore();
        $successCount = 0;
        
        foreach ($chunks as $index => $chunk) {
            try {
                $vector = $engine->embed($chunk['text']);
                $store->insert([
                    'vector' => $vector,
                    'content' => $chunk['text'],
                    'metadata' => [
                        'file_hash' => $fileHash,
                        'doc_type' => $docType,
                        'related_id' => $relatedId,
                        'chunk_index' => $index,
                    ],
                ]);
                $successCount++;
            } catch (\Exception $e) {
                // 单块失败继续
            }
        }
        
        // ========== 记录哈希 ==========
        $documentId = FileHasher::recordVectorized(
            $fileHash, $filePath, $docType, $successCount, $relatedId
        );
        
        return [
            'status' => 'completed',
            'document_id' => $documentId,
            'total_chunks' => count($chunks),
            'success_count' => $successCount,
        ];
    }
}
```

---

## 使用示例

### 基础调用

```php
// 1. 最简单的调用
$result = AIManager::run('worktime_estimate', [
    'requirement' => '开发用户登录模块',
    'complexity' => 'medium'
]);

// 2. 流式输出（打字机效果）
AIManager::stream('report_generate', $input, function($chunk) {
    echo "data: " . json_encode(['content' => $chunk]) . "\n\n";
    flush();
});

// 3. Agent 模式（带工具调用）
$result = AIManager::agent('project_assistant')
    ->withTools(['database_query', 'get_contract', 'calculator'])
    ->withMemory('user-123')
    ->ask('这个项目的预算使用率是多少？');

// 4. 异步执行
$jobId = AIManager::async('contract_review', $contractData)
    ->priority(8)
    ->onComplete(fn($result) => NotifyService::send($userId, $result))
    ->dispatch();

// 5. 指定模型
$result = AIManager::using('openai')
    ->run('complex_analysis', $data);
```

### 异步任务

```php
// 上传合同后异步向量化
$jobId = AIJobDispatcher::dispatch(
    new VectorizeDocJob('/uploads/contract.pdf', 'contract', 123, $userId)
);

// 合同审查
$jobId = AIJobDispatcher::dispatch(
    new ContractReviewJob($contractId, $content, $userId)
);

// 批量工时估算
$jobId = AIJobDispatcher::dispatch(
    new BatchEstimateJob([101, 102, 103], $userId)
);
```

---

## 数据库设计

### AI 任务表

```sql
CREATE TABLE ai_jobs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    task_type VARCHAR(50) NOT NULL,
    payload JSON NOT NULL,
    priority INT NOT NULL DEFAULT 5,
    max_retries INT NOT NULL DEFAULT 3,
    retry_count INT NOT NULL DEFAULT 0,
    timeout INT NOT NULL DEFAULT 300,
    user_id INT DEFAULT NULL,
    status ENUM('pending','processing','completed','failed','dead') DEFAULT 'pending',
    result JSON DEFAULT NULL,
    error_message TEXT DEFAULT NULL,
    scheduled_at DATETIME DEFAULT NULL,
    started_at DATETIME DEFAULT NULL,
    completed_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL,
    
    INDEX idx_status_priority (status, priority DESC, created_at),
    INDEX idx_user (user_id),
    INDEX idx_scheduled (scheduled_at)
) ENGINE=InnoDB;
```

### 向量化文档表

```sql
CREATE TABLE vectorized_documents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    file_hash VARCHAR(64) NOT NULL UNIQUE,
    file_path VARCHAR(500) NOT NULL,
    doc_type VARCHAR(50) NOT NULL,
    related_id INT DEFAULT NULL,
    chunk_count INT NOT NULL DEFAULT 0,
    vectorized_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    
    INDEX idx_file_hash (file_hash),
    INDEX idx_doc_type (doc_type)
) ENGINE=InnoDB;

CREATE TABLE vectorized_document_relations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    document_id INT NOT NULL,
    relation_type VARCHAR(50) NOT NULL,
    related_id INT NOT NULL,
    created_at DATETIME NOT NULL,
    
    UNIQUE KEY uk_relation (document_id, relation_type, related_id),
    FOREIGN KEY (document_id) REFERENCES vectorized_documents(id) ON DELETE CASCADE
) ENGINE=InnoDB;
```

### AI 用量统计表

```sql
CREATE TABLE ai_usage_logs (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id INT DEFAULT NULL,
    task_type VARCHAR(50) NOT NULL,
    provider VARCHAR(30) NOT NULL,
    model VARCHAR(50) NOT NULL,
    input_tokens INT NOT NULL DEFAULT 0,
    output_tokens INT NOT NULL DEFAULT 0,
    total_tokens INT NOT NULL DEFAULT 0,
    cost DECIMAL(10,6) NOT NULL DEFAULT 0,
    latency_ms INT NOT NULL DEFAULT 0,
    status ENUM('success','failed') NOT NULL,
    created_at DATETIME NOT NULL,
    
    INDEX idx_user_date (user_id, created_at),
    INDEX idx_provider (provider, created_at),
    INDEX idx_task_type (task_type, created_at)
) ENGINE=InnoDB;
```

---

## 开发规范

### 1. 新增 Provider

```php
// 1. 创建新文件 src/Services/AI/Providers/NewProvider.php
// 2. 继承 AbstractProvider
// 3. 实现 ProviderInterface 所有方法
// 4. 在 config/ai.php 中注册
```

### 2. 新增 Tool

```php
// 1. 创建新文件 src/Services/AI/Tools/Business/NewTool.php
// 2. 继承 BaseTool
// 3. 实现 getName(), getDescription(), getParameters(), execute()
// 4. 在 ToolRegistry 中注册
```

### 3. 新增 Task

```php
// 1. 创建新文件 src/Services/AI/Tasks/NewTask.php
// 2. 继承 BaseTask
// 3. 配置 model, temperature, tools
// 4. 实现 getPromptTemplate()
```

### 4. 新增 Job

```php
// 1. 创建新文件 src/Services/AI/Queue/Jobs/NewJob.php
// 2. 继承 BaseJob
// 3. 配置 type, priority, timeout
// 4. 实现 handle()
```

---

## 省钱技巧

| 技巧 | 节省比例 | 实现方式 |
|------|---------|---------|
| 文件哈希去重 | 50%+ | `FileHasher::findByHash()` |
| 模型路由 | 30%+ | 简单任务用便宜模型 |
| 摘要记忆 | 20%+ | 压缩长对话历史 |
| 限流保护 | 防滥用 | `RateLimitPipe` |
| Token 告警 | 防超支 | `AlertService` |

---

> **文档版本**: V7.3 Pro Final  
> **最后更新**: 2025-12-10  
> **维护者**: CRM_ERP 开发团队