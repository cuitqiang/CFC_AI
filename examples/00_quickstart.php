<?php
declare(strict_types=1);

/**
 * 快速开始示例
 * 使用 Bootstrap 快速初始化系统
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Services\AI\Bootstrap;

// 一键初始化整个系统
Bootstrap::initialize(__DIR__ . '/../.env');

// 获取 AIManager
$aiManager = Bootstrap::getAIManager();

echo "=== 快速开始示例 ===\n\n";

// 直接使用
$response = $aiManager->process("你好，请介绍一下你自己", [
    'user_id' => 'quickstart_user',
    'model' => 'deepseek-chat',
]);

if ($response['success']) {
    echo "AI: {$response['message']}\n\n";
} else {
    echo "错误: {$response['error']}\n\n";
}

// 使用其他组件
$costCalculator = Bootstrap::getCostCalculator();
$usageTracker = Bootstrap::getUsageTracker();

// 计算成本
$cost = $costCalculator->calculateCost('deepseek-chat', 500, 200);
echo "估算成本: $" . number_format($cost, 6) . "\n\n";

echo "=== 系统已就绪 ===\n";
