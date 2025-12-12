<?php
/**
 * CRM系统集成示例
 * 演示如何在实际业务中使用AI Agent
 */

require_once __DIR__ . '/vendor/autoload.php';

use Services\AI\Bootstrap;

// 初始化
Bootstrap::initialize();
$aiManager = Bootstrap::getAIManager();

echo "=== CRM系统AI集成示例 ===\n\n";

// ========================================
// 场景 1: 客户咨询自动回复
// ========================================
function handleCustomerInquiry(string $customerMessage, string $customerId): array
{
    global $aiManager;

    $prompt = "你是一个专业的CRM客服助手。客户问：{$customerMessage}";

    $result = $aiManager->process($prompt, [
        'user_id' => $customerId,
        'context' => 'customer_service',
        'model' => 'deepseek-v3'
    ]);

    return $result;
}

echo "【场景 1】客户咨询处理\n";
$inquiry = handleCustomerInquiry(
    "我想了解你们的VIP会员有什么优惠？",
    "customer_001"
);
echo "客户问题：我想了解你们的VIP会员有什么优惠？\n";
echo "AI回复：" . ($inquiry['response'] ?? $inquiry['message'] ?? '无响应') . "\n\n";

// ========================================
// 场景 2: 合同内容分析
// ========================================
function analyzeContract(string $contractText): array
{
    global $aiManager;

    $prompt = "请分析以下合同内容，提取关键信息：\n{$contractText}";

    $result = $aiManager->process($prompt, [
        'context' => 'contract_analysis',
        'model' => 'deepseek-v3'
    ]);

    return $result;
}

echo "【场景 2】合同分析\n";
$contractAnalysis = analyzeContract(
    "甲方与乙方签订为期一年的软件开发合同，总金额50万元，分三期付款..."
);
echo "AI分析：" . ($contractAnalysis['response'] ?? $contractAnalysis['message'] ?? '无响应') . "\n\n";

// ========================================
// 场景 3: 销售数据总结
// ========================================
function summarizeSalesData(array $salesData): array
{
    global $aiManager;

    $dataText = json_encode($salesData, JSON_UNESCAPED_UNICODE);
    $prompt = "请分析以下销售数据并给出总结和建议：\n{$dataText}";

    $result = $aiManager->process($prompt, [
        'context' => 'sales_analysis',
        'model' => 'deepseek-v3'
    ]);

    return $result;
}

echo "【场景 3】销售数据分析\n";
$salesSummary = summarizeSalesData([
    '本月销售额' => '100万',
    '上月销售额' => '80万',
    '增长率' => '25%',
    '客户数量' => 150
]);
echo "数据：本月100万，上月80万，增长25%\n";
echo "AI分析：" . ($salesSummary['response'] ?? $salesSummary['message'] ?? '无响应') . "\n\n";

// ========================================
// 场景 4: 智能任务分配
// ========================================
function assignTaskWithAI(string $taskDescription, array $teamMembers): array
{
    global $aiManager;

    $membersText = implode(', ', $teamMembers);
    $prompt = "任务：{$taskDescription}\n团队成员：{$membersText}\n请建议如何分配这个任务。";

    $result = $aiManager->process($prompt, [
        'context' => 'task_assignment',
        'model' => 'deepseek-v3'
    ]);

    return $result;
}

echo "【场景 4】智能任务分配\n";
$taskAssignment = assignTaskWithAI(
    "开发一个客户管理模块",
    ['张三(后端)', '李四(前端)', '王五(测试)']
);
echo "任务：开发客户管理模块\n";
echo "团队：张三(后端)、李四(前端)、王五(测试)\n";
echo "AI建议：" . ($taskAssignment['response'] ?? $taskAssignment['message'] ?? '无响应') . "\n\n";

echo "=== 集成示例完成 ===\n";
