📘 CRM_ERP_V7.6 技术交付与维护手册 (Ultimate)
版本: V7.6 Production 适用对象: 后端开发、运维工程师、架构师 核心目标: 确保系统可部署、可监控、可扩展

1. 🗄️ 数据库架构详解 (Database Schema)
这是之前蓝图中没有详细列出的底层数据结构。请直接在 MySQL 中执行。

1.1 AI 用量审计表 (ai_usage_logs)
用于精算成本，每一分钱 Token 都要记账。

SQL
CREATE TABLE `ai_usage_logs` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT '调用用户ID',
  `trace_id` varchar(64) NOT NULL COMMENT '全链路追踪ID',
  `provider` varchar(32) NOT NULL COMMENT '服务商(deepseek/openai)',
  `model` varchar(64) NOT NULL COMMENT '模型名称',
  `prompt_tokens` int(11) DEFAULT 0 COMMENT '提问消耗',
  `completion_tokens` int(11) DEFAULT 0 COMMENT '回答消耗',
  `total_cost` decimal(10,6) DEFAULT 0.000000 COMMENT '总成本(元)',
  `duration_ms` int(11) DEFAULT 0 COMMENT '耗时(毫秒)',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_time` (`user_id`, `created_at`),
  KEY `idx_trace` (`trace_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AI调用审计日志';
1.2 RAG 向量存储表 (ai_vectors)
用于“企业大脑”，存储切片后的文档知识。

SQL
CREATE TABLE `ai_vectors` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `doc_hash` char(32) NOT NULL COMMENT '文件MD5去重',
  `file_path` varchar(255) NOT NULL COMMENT '原始文件路径',
  `chunk_index` int(11) NOT NULL COMMENT '切片序号',
  `content` text NOT NULL COMMENT '切片文本内容',
  `embedding` json NOT NULL COMMENT '1536维向量数据',
  `metadata` json DEFAULT NULL COMMENT '额外元数据(页码/作者)',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hash` (`doc_hash`)
  -- 注意：如果用 pgvector 或 Milvus，此表结构会不同
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='RAG向量知识库';
1.3 异步任务队列表 (ai_jobs)
用于处理耗时任务，防止超时。

SQL
CREATE TABLE `ai_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `queue` varchar(32) NOT NULL DEFAULT 'default',
  `payload` longtext NOT NULL COMMENT '任务数据JSON',
  `attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 0 COMMENT '重试次数',
  `reserved_at` int(10) UNSIGNED DEFAULT NULL COMMENT '被谁锁定',
  `available_at` int(10) UNSIGNED NOT NULL COMMENT '何时可用',
  `created_at` int(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_queue` (`queue`, `reserved_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AI异步任务队列';
2. ⚙️ 核心配置详解 (Configuration)
2.1 环境变量 (.env)
敏感信息绝不硬编码。

Ini, TOML
# AI 核心配置
AI_DEFAULT_PROVIDER=deepseek  # 默认模型商
AI_TIMEOUT=120                # 接口超时时间(秒)

# Deepseek 配置
DEEPSEEK_API_KEY=sk-xxxxxxxxx
DEEPSEEK_MODEL=deepseek-chat
DEEPSEEK_BASE_URL=https://api.deepseek.com/v1/

# OpenAI 配置 (备用)
OPENAI_API_KEY=sk-xxxxxxxxx
OPENAI_MODEL=gpt-4-turbo

# 向量模型配置
EMBEDDING_PROVIDER=openai     # 或 deepseek (如果支持)
EMBEDDING_MODEL=text-embedding-3-small

# 监控报警
AI_COST_LIMIT_DAILY=50.00     # 每日消费上限(元)
AI_ALERT_EMAIL=admin@company.com
2.2 角色人设配置 (src/Config/agents.php)
随时调整 AI 性格，无需改代码。

PHP
return [
    // 辩论赛 - 正方
    'debate_pro' => [
        'name' => '正方一辩',
        'model' => 'deepseek-chat',
        'temperature' => 0.8,
        'system_prompt' => '你是一个逻辑严密的辩手，请仅从正面论证观点，使用数据支撑...',
    ],
    // 合同审查员
    'contract_auditor' => [
        'name' => '法务AI',
        'model' => 'gpt-4-turbo', // 用更聪明的模型
        'temperature' => 0.2,      // 严谨，不发散
        'system_prompt' => '你是资深法务，请找出合同中的风险条款，特别是关于赔偿责任的部分...',
    ]
];
3. 🔌 接口对接规范 (API Specs)
前端对接时，只需要看这一部分。

3.1 辩论/对话接口 (流式 SSE)
Endpoint: GET /api/ai/debate/stream

Headers:

Accept: text/event-stream

Params:

topic: (string) 辩论主题

agent: (string) 指定角色 key (可选)

Response (Stream):

Plaintext
event: start
data: {"msg": "辩论开始"}

event: chunk
data: {"agent": "pro", "content": "我认为", "done": false}

event: chunk
data: {"agent": "pro", "content": "远程办公", "done": false}

event: tool_use
data: {"tool": "search", "query": "2024年远程办公数据"}

event: end
data: {"total_tokens": 150}
4. 🛠️ 二次开发指南 (Extension Guide)
这是给新来的程序员看的“保姆教程”。

场景：老板想加一个“查询天气”的功能
Step 1: 创建工具类 新建 src/Services/AI/Tools/System/WeatherTool.php：

PHP
class WeatherTool extends BaseTool {
    public function name(): string { return 'get_weather'; }
    public function schema(): array {
        return [
            'type' => 'object',
            'properties' => [
                'city' => ['type' => 'string', 'description' => '城市名']
            ]
        ];
    }
    public function run(array $args): string {
        // 调用第三方天气 API
        return "北京今天晴，25度";
    }
}
Step 2: 注册工具 修改 src/Services/AI/Tools/ToolRegistry.php：

PHP
self::register(new WeatherTool());
Step 3: 完成！ 现在你只需要对 AI 说：“帮我查查北京天气”，它就会自动调用这个工具。

5. 🚀 部署运维 (Deployment)
5.1 Nginx 配置优化
为了防止 SSE 流式输出被缓存卡顿。

Nginx
location /api/ai/ {
    try_files $uri $uri/ /index.php?$query_string;
    
    # 关键配置：禁用缓冲，否则流式输出会变成一次性输出
    proxy_buffering off;
    fastcgi_buffering off;
    
    # 长连接设置
    keepalive_timeout 0;
}
5.2 队列守护进程 (Supervisor)
确保异步任务一直有人干活。

Ini, TOML
[program:ai-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/project/artisan ai:work --queue=default
autostart=true
autorestart=true
user=www
numprocs=2
redirect_stderr=true