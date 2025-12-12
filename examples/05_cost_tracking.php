<?php
declare(strict_types=1);

/**
 * 成本追踪和分析示例
 * 演示如何使用成本计算和使用量追踪
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Services\AI\Config;
use Services\AI\Analytics\CostCalculator;
use Services\AI\Analytics\UsageTracker;

// 加载配置
Config::load(__DIR__ . '/../.env');

echo "=== AI Agent 成本追踪示例 ===\n\n";

// 1. 初始化
$costCalculator = new CostCalculator();
$usageTracker = new UsageTracker();

// 2. 模拟一些 API 调用
echo "--- 模拟 API 调用 ---\n\n";

$calls = [
    ['model' => 'deepseek-chat', 'input' => 500, 'output' => 200, 'latency' => 1.2],
    ['model' => 'deepseek-chat', 'input' => 800, 'output' => 300, 'latency' => 1.5],
    ['model' => 'gpt-4o-mini', 'input' => 600, 'output' => 250, 'latency' => 0.8],
    ['model' => 'deepseek-chat', 'input' => 1000, 'output' => 400, 'latency' => 1.8],
    ['model' => 'gpt-4o-mini', 'input' => 700, 'output' => 280, 'latency' => 0.9],
];

foreach ($calls as $i => $call) {
    $cost = $costCalculator->calculateCost(
        $call['model'],
        $call['input'],
        $call['output']
    );

    $usageTracker->track(
        $call['model'],
        $call['input'],
        $call['output'],
        $call['latency'],
        true
    );

    echo "调用 " . ($i + 1) . ": {$call['model']}\n";
    echo "  输入: {$call['input']} tokens\n";
    echo "  输出: {$call['output']} tokens\n";
    echo "  成本: $" . number_format($cost, 6) . "\n";
    echo "  延迟: {$call['latency']}s\n\n";
}

// 3. 使用统计
echo "--- 使用统计 ---\n\n";

$stats = $usageTracker->getStats();

foreach ($stats as $model => $data) {
    echo "{$model}:\n";
    echo "  总请求: {$data['total_requests']}\n";
    echo "  成功率: " . number_format($data['success_rate'], 2) . "%\n";
    echo "  总 tokens: {$data['total_tokens']}\n";
    echo "  平均输入: " . number_format($data['avg_input_tokens'], 0) . " tokens\n";
    echo "  平均输出: " . number_format($data['avg_output_tokens'], 0) . " tokens\n";
    echo "  平均延迟: " . number_format($data['avg_latency'], 3) . "s\n\n";
}

// 4. 总成本计算
echo "--- 总成本计算 ---\n\n";

$usage = [];
foreach ($stats as $model => $data) {
    $usage[$model] = [
        'input_tokens' => $data['total_input_tokens'],
        'output_tokens' => $data['total_output_tokens'],
    ];
}

$totalCost = $costCalculator->calculateTotalCost($usage);

echo "总成本: $" . number_format($totalCost['total_cost'], 6) . "\n\n";

echo "成本明细:\n";
foreach ($totalCost['breakdown'] as $model => $detail) {
    echo "  {$model}: $" . number_format($detail['cost'], 6) . "\n";
}

echo "\n";

// 5. 模型对比
echo "--- 模型成本对比 ---\n\n";

$comparison = $costCalculator->compareCosts(
    'gpt-4o-mini',
    'deepseek-chat',
    1000,
    500
);

echo "对比 (1000 输入 + 500 输出 tokens):\n";
echo "  {$comparison['model1']['name']}: $" . number_format($comparison['model1']['cost'], 6) . "\n";
echo "  {$comparison['model2']['name']}: $" . number_format($comparison['model2']['cost'], 6) . "\n";
echo "  节省: $" . number_format($comparison['savings'], 6) . " (" . number_format($comparison['savings_percent'], 2) . "%)\n";
echo "  更便宜的模型: {$comparison['cheaper_model']}\n\n";

// 6. 月度成本预测
echo "--- 月度成本预测 ---\n\n";

$prediction = $costCalculator->predictMonthlyCost(
    'deepseek-chat',
    1000,  // 每天 1000 次请求
    500,   // 平均 500 输入 tokens
    200    // 平均 200 输出 tokens
);

echo "模型: {$prediction['model']}\n";
echo "每日请求: {$prediction['daily_requests']}\n";
echo "每次请求成本: $" . number_format($prediction['cost_per_request'], 6) . "\n";
echo "每日成本: $" . number_format($prediction['daily_cost'], 2) . "\n";
echo "每月成本: $" . number_format($prediction['monthly_cost'], 2) . "\n\n";

// 7. 趋势分析
echo "--- 趋势分析 ---\n\n";

$trend = $usageTracker->getTrend('deepseek-chat', 7);

echo "模型: deepseek-chat\n";
echo "趋势: {$trend['trend']}\n";
echo "变化: " . number_format($trend['change_percent'], 2) . "%\n\n";

// 8. 导出数据
echo "--- 导出统计数据 ---\n\n";

$exportJson = $usageTracker->export('json');
file_put_contents(__DIR__ . '/usage_stats.json', $exportJson);
echo "已导出 JSON: usage_stats.json\n";

$exportCsv = $usageTracker->export('csv');
file_put_contents(__DIR__ . '/usage_stats.csv', $exportCsv);
echo "已导出 CSV: usage_stats.csv\n\n";

echo "=== 示例结束 ===\n";
