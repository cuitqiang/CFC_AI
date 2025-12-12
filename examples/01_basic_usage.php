<?php
declare(strict_types=1);

/**
 * 基础使用示例
 * 演示如何使用 AI Agent 系统进行简单对话
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Services\AI\Config;
use Services\AI\Core\AIManager;
use Services\AI\Core\ModelRouter;
use Services\AI\Providers\DeepseekProvider;
use Services\AI\Providers\OpenAIProvider;
use Services\AI\Tools\ToolRegistry;
use Services\AI\Memory\ContextManager;
use Services\AI\Memory\ShortTerm;
use Services\AI\Memory\Summary;
use Services\AI\Memory\VectorStore;

// 1. 加载配置
Config::load(__DIR__ . '/../.env');

// 2. 初始化 Providers
$deepseekProvider = new DeepseekProvider(
    Config::get('deepseek.api_key'),
    Config::get('deepseek.base_url')
);

$openaiProvider = new OpenAIProvider(
    Config::get('openai.api_key'),
    Config::get('openai.base_url')
);

// 3. 初始化 ModelRouter
$modelRouter = new ModelRouter();
$modelRouter->register('deepseek', $deepseekProvider);
$modelRouter->register('openai', $openaiProvider);
$modelRouter->setDefaultProvider('deepseek');

// 4. 初始化 ToolRegistry（暂时为空）
$toolRegistry = new ToolRegistry();

// 5. 初始化 Memory 系统
$shortTerm = new ShortTerm(Config::get('memory.ttl'));
$summary = new Summary();
$vectorStore = new VectorStore();
$contextManager = new ContextManager(
    $shortTerm,
    $summary,
    $vectorStore,
    Config::get('memory.max_short_term_messages')
);

// 6. 初始化 AIManager
$aiManager = new AIManager(
    $modelRouter,
    $toolRegistry,
    $contextManager,
    Config::all()
);

// 7. 发送请求
echo "=== AI Agent 基础使用示例 ===\n\n";

$userInput = "你好！请介绍一下你自己。";
echo "用户: {$userInput}\n\n";

$response = $aiManager->process($userInput, [
    'user_id' => 'user_001',
    'session_id' => 'session_' . time(),
    'model' => 'deepseek-chat',
]);

if ($response['success']) {
    echo "AI: {$response['message']}\n\n";
} else {
    echo "错误: {$response['error']}\n\n";
}

// 8. 多轮对话
echo "--- 多轮对话 ---\n\n";

$sessionId = 'session_' . time();
$userId = 'user_001';

$conversations = [
    "我喜欢吃披萨",
    "我上次说我喜欢吃什么？",
    "给我推荐一家披萨店",
];

foreach ($conversations as $input) {
    echo "用户: {$input}\n";

    $response = $aiManager->process($input, [
        'user_id' => $userId,
        'session_id' => $sessionId,
        'model' => 'deepseek-chat',
    ]);

    if ($response['success']) {
        echo "AI: {$response['message']}\n\n";
    } else {
        echo "错误: {$response['error']}\n\n";
    }

    // 模拟延迟
    sleep(1);
}

echo "=== 示例结束 ===\n";
