<?php
/**
 * CFC V7.7 路由配置
 * 
 * RESTful 风格路由定义
 * 
 * CFC V7.7 规范：
 * - 依赖在路由层统一实例化（IoC 容器模式）
 * - Controller 通过构造函数注入依赖
 * - 禁止在 Controller 内部 new 服务
 * 
 * 路由注册方式：
 * - Case 1: 闭包 - $router->get('/path', fn($req) => ...)
 * - Case 2: 对象实例（推荐）- $router->get('/path', [$instance, 'method'])
 * - Case 3: 类名（仅无依赖控制器）- $router->get('/path', [Controller::class, 'method'])
 */
declare(strict_types=1);

use App\Controllers\DebateController;
use App\Controllers\SystemController;
use App\Controllers\RagController;
use App\Controllers\CuigeController;
use Services\AI\Bootstrap;
use Services\AI\Core\RAG\VectorService;
use Services\AI\Document\SmartDocumentProcessor;
use Services\AI\Cuige\CuigeService;
use Services\AI\Cuige\CuigeRepository;
use Services\AI\Cuige\CuigeConfig;

// ==========================================================================
// 1. 服务装配区 (Service Assembly) - 简易 IoC 容器
// ==========================================================================

// 获取 PostgreSQL 连接（单例）- RAG 用
$pdo = Bootstrap::getPgPdo();

// 获取 MySQL 连接（单例）- 崔哥 AI 用
$mysqlPdo = Bootstrap::getPDO();

// 初始化向量服务（注入 PDO）
$vectorService = new VectorService(
    $pdo,
    null,   // embedding provider (使用 LocalEmbedding)
    512,    // chunk_size
    50,     // chunk_overlap
    'pgsql',
    true    // 使用 pgvector
);

// 初始化文档处理器（注入向量服务）
$docProcessor = new SmartDocumentProcessor($vectorService);

// 崔哥 AI 服务装配（注入 VectorService 实现 RAG 知识库访问）
$cuigeConfig = new CuigeConfig();
$cuigeRepo = new CuigeRepository($mysqlPdo);
$modelRouter = Bootstrap::getModelRouter();
$cuigeService = new CuigeService($cuigeRepo, $modelRouter, $cuigeConfig, $vectorService);

// ==========================================================================
// 2. 控制器实例化 (Controller Instantiation) - 依赖注入
// ==========================================================================

$ragController = new RagController($vectorService, $docProcessor);
$cuigeController = new CuigeController($cuigeService, $cuigeConfig);

// ==========================================================================
// 3. 路由注册 (Route Registration) - 传递活的对象实例
// ==========================================================================

// ========== 辩论系统路由（无依赖，使用类名模式）==========
$router->get('/api/debate/stream', [DebateController::class, 'stream']);
$router->post('/api/debate/stream-with-image', [DebateController::class, 'streamWithImage']);
$router->post('/api/debate/analyze-image', [DebateController::class, 'analyzeImage']);
$router->post('/api/debate/speak', [DebateController::class, 'speak']);
$router->get('/api/debate/agents', [DebateController::class, 'listAgents']);

// ========== 系统路由（无依赖，使用类名模式）==========
$router->get('/api/health', [SystemController::class, 'health']);

// ========== 崔哥 AI 路由（CFC V7.7 对象实例模式）==========
$router->get('/api/cuige/health', [$cuigeController, 'health']);
$router->post('/api/cuige/chat', [$cuigeController, 'chat']);
$router->get('/api/cuige/stream', [$cuigeController, 'stream']);  // SSE 流式聊天
$router->get('/api/cuige/sessions', [$cuigeController, 'sessions']);  // 会话列表
$router->get('/api/cuige/history', [$cuigeController, 'history']);
$router->get('/api/cuige/memories', [$cuigeController, 'memories']);
$router->get('/api/cuige/profile', [$cuigeController, 'profile']);
$router->get('/api/cuige/context', [$cuigeController, 'contextStatus']);
$router->post('/api/cuige/tts', [$cuigeController, 'tts']);

// ========== RAG 知识库路由（CFC V7.7 对象实例模式）==========
// 文档上传（智能处理：支持 PDF/图片/文本，自动选择最优策略）
$router->post('/api/rag/upload', [$ragController, 'upload']);

// 语义搜索
$router->post('/api/rag/search', [$ragController, 'search']);

// 文档管理
$router->get('/api/rag/documents', [$ragController, 'listDocuments']);
$router->delete('/api/rag/documents/{hash}', [$ragController, 'deleteDocument']);

// 系统信息
$router->get('/api/rag/stats', [$ragController, 'stats']);
$router->get('/api/rag/capabilities', [$ragController, 'capabilities']);

// ==========================================================================
// 4. 通用路由
// ==========================================================================

// API 根路径
$router->get('/api', function () {
    return [
        'success' => true,
        'message' => 'CFC V7.7 AI Agent API',
        'version' => '7.7.0',
        'endpoints' => [
            // 辩论系统
            'GET /api/debate/stream?topic=xxx&mode=chat' => '流式辩论',
            'POST /api/debate/speak' => '用户发言',
            'GET /api/debate/agents?mode=chat' => '获取 Agents 列表',
            // 系统
            'GET /api/health' => '健康检查',
            // 崔哥 AI
            'GET /api/cuige/health' => '崔哥健康检查',
            'POST /api/cuige/chat' => '聊天对话',
            'GET /api/cuige/stream?message=xxx&session_id=xxx' => 'SSE 流式聊天',
            'GET /api/cuige/history?session_id=xxx' => '历史记录',
            'GET /api/cuige/memories?user_id=xxx' => '用户记忆',
            'GET /api/cuige/profile?user_id=xxx' => '用户画像',
            'GET /api/cuige/context?session_id=xxx' => '上下文状态',
            'POST /api/cuige/tts' => '语音合成',
            // RAG 知识库
            'POST /api/rag/upload' => '上传文档',
            'POST /api/rag/search' => '语义搜索',
            'GET /api/rag/documents' => '文档列表',
            'DELETE /api/rag/documents/{hash}' => '删除文档',
            'GET /api/rag/stats' => '统计信息',
            'GET /api/rag/capabilities' => '系统能力',
        ],
    ];
});

// 根路径
$router->get('/', function () {
    return [
        'success' => true,
        'message' => 'Welcome to CFC V7.7',
        'api' => '/api',
    ];
});
