<?php
/**
 * 实用调用示例集合
 */

require_once __DIR__ . '/vendor/autoload.php';

use Services\AI\Bootstrap;

// 初始化系统
Bootstrap::initialize();
$aiManager = Bootstrap::getAIManager();

echo "=== CRM_AI_V7.6 实用调用示例 ===\n\n";

// ========================================
// 示例 1: 基础问答
// ========================================
echo "【示例 1】基础问答\n";
echo "-------------------\n";

$result1 = $aiManager->process("PHP 8.3有哪些新特性？");
echo "问题：PHP 8.3有哪些新特性？\n";
echo "回答：" . ($result1['response'] ?? $result1['message'] ?? '无响应') . "\n\n";

// ========================================
// 示例 2: 带用户ID的对话（用于追踪）
// ========================================
echo "【示例 2】带用户信息的对话\n";
echo "-------------------\n";

$result2 = $aiManager->process(
    "帮我分析一下这个月的销售数据",
    [
        'user_id' => 'user_12345',
        'session_id' => 'session_' . time(),
        'model' => 'deepseek-v3'
    ]
);
echo "回答：" . ($result2['response'] ?? $result2['message'] ?? '无响应') . "\n\n";

// ========================================
// 示例 3: 指定使用的模型
// ========================================
echo "【示例 3】指定模型\n";
echo "-------------------\n";

$result3 = $aiManager->process(
    "写一个Python快速排序算法",
    ['model' => 'deepseek-v3']
);
echo "回答：" . ($result3['response'] ?? $result3['message'] ?? '无响应') . "\n\n";

// ========================================
// 示例 4: 使用工具（日期计算）
// ========================================
echo "【示例 4】使用工具计算日期\n";
echo "-------------------\n";

$toolRegistry = Bootstrap::getToolRegistry();
$result4 = $aiManager->process("今天是几号？距离2025年春节还有多少天？");
echo "回答：" . ($result4['response'] ?? $result4['message'] ?? '无响应') . "\n\n";

// ========================================
// 示例 5: 成本追踪
// ========================================
echo "【示例 5】查看API调用成本\n";
echo "-------------------\n";

$costCalculator = Bootstrap::getCostCalculator();
$cost = $costCalculator->calculate('deepseek-v3', 1000, 500);
echo "模型：deepseek-v3\n";
echo "输入Token：1000，输出Token：500\n";
echo "预估成本：$" . number_format($cost, 6) . "\n\n";

echo "=== 所有示例完成 ===\n";
