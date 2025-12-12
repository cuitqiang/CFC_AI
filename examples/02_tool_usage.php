<?php
declare(strict_types=1);

/**
 * 工具调用示例
 * 演示如何注册和使用工具
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Services\AI\Config;
use Services\AI\Core\AIManager;
use Services\AI\Core\ModelRouter;
use Services\AI\Providers\DeepseekProvider;
use Services\AI\Tools\ToolRegistry;
use Services\AI\Tools\System\TimeCalculator;
use Services\AI\Tools\System\DatabaseReader;
use Services\AI\Memory\ContextManager;
use Services\AI\Memory\ShortTerm;
use Services\AI\Memory\Summary;
use Services\AI\Memory\VectorStore;

// 加载配置
Config::load(__DIR__ . '/../.env');

// 初始化 Provider
$deepseekProvider = new DeepseekProvider(
    Config::get('deepseek.api_key'),
    Config::get('deepseek.base_url')
);

$modelRouter = new ModelRouter();
$modelRouter->register('deepseek', $deepseekProvider);

// 初始化 ToolRegistry 并注册工具
$toolRegistry = new ToolRegistry();
$toolRegistry->register(new TimeCalculator());
$toolRegistry->register(new DatabaseReader());

// 初始化 Memory
$contextManager = new ContextManager(
    new ShortTerm(),
    new Summary(),
    new VectorStore()
);

// 初始化 AIManager
$aiManager = new AIManager(
    $modelRouter,
    $toolRegistry,
    $contextManager
);

echo "=== AI Agent 工具调用示例 ===\n\n";

// 示例 1: 时间计算
echo "--- 示例 1: 计算日期差 ---\n\n";

$input1 = "帮我计算从 2024-01-01 到 2024-12-31 之间有多少天？";
echo "用户: {$input1}\n\n";

$response1 = $aiManager->process($input1, [
    'user_id' => 'user_002',
    'model' => 'deepseek-chat',
]);

if ($response1['success']) {
    echo "AI: {$response1['message']}\n\n";
} else {
    echo "错误: {$response1['error']}\n\n";
}

// 示例 2: 工作日计算
echo "--- 示例 2: 计算工作日 ---\n\n";

$input2 = "从 2024-11-01 到 2024-11-30 有多少个工作日？";
echo "用户: {$input2}\n\n";

$response2 = $aiManager->process($input2, [
    'user_id' => 'user_002',
    'model' => 'deepseek-chat',
]);

if ($response2['success']) {
    echo "AI: {$response2['message']}\n\n";
} else {
    echo "错误: {$response2['error']}\n\n";
}

// 示例 3: 获取当前时间
echo "--- 示例 3: 获取当前时间 ---\n\n";

$input3 = "现在几点了？";
echo "用户: {$input3}\n\n";

$response3 = $aiManager->process($input3, [
    'user_id' => 'user_002',
    'model' => 'deepseek-chat',
]);

if ($response3['success']) {
    echo "AI: {$response3['message']}\n\n";
} else {
    echo "错误: {$response3['error']}\n\n";
}

echo "=== 示例结束 ===\n";
