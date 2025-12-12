<?php
/**
 * 多Agent协同工作演示
 * 展示如何让多个AI Agent并行或协作完成任务
 */

require_once __DIR__ . '/vendor/autoload.php';

use Services\AI\Bootstrap;
use Services\AI\Queue\AIJobDispatcher;
use Services\AI\Queue\Jobs\RunAgentJob;

Bootstrap::initialize();

$aiManager = Bootstrap::getAIManager();
$dispatcher = Bootstrap::getDispatcher();

echo "========================================\n";
echo "多Agent协同工作演示\n";
echo "========================================\n\n";

// ========================================
// 方式1: 并行执行多个Agent（异步队列）
// ========================================
echo "【方式1】异步并行执行多个Agent\n";
echo "-----------------------------------\n";


// 场景：同时分析一个项目的多个方面
$projectData = [
    'project_id' => 12345,
    'name' => '客户管理系统开发',
    'budget' => 500000,
    'deadline' => '2025-03-31'
];

echo "场景：同时分析项目的多个维度\n\n";

// Agent 1: 合同审查
echo "启动 Agent 1: 合同审查...\n";
$job1 = new RunAgentJob(
    $aiManager,
    '请审查以下合同：甲方与乙方签订软件开发合同，总金额50万元...',
    ['task_type' => 'contract_review', 'contract_id' => 123]
);
$jobId1 = $dispatcher->dispatch($job1);
echo "  任务ID: $jobId1\n";

// Agent 2: 工时估算
echo "启动 Agent 2: 工时估算...\n";
$job2 = new RunAgentJob(
    $aiManager,
    '估算工时：开发完整的客户管理系统，包括前后端，复杂度：高',
    ['task_type' => 'worktime_estimate']
);
$jobId2 = $dispatcher->dispatch($job2);
echo "  任务ID: $jobId2\n";

// Agent 3: 风险分析
echo "启动 Agent 3: 风险分析...\n";
$job3 = new RunAgentJob(
    $aiManager,
    '分析项目风险：' . json_encode($projectData),
    ['task_type' => 'risk_analysis']
);
$jobId3 = $dispatcher->dispatch($job3);
echo "  任务ID: $jobId3\n\n";

echo "✅ 3个Agent已并行启动！\n";
echo "提示：使用 AIJobWorker 来处理这些任务\n\n";

// ========================================
// 方式2: 顺序协作（管道式）
// ========================================
echo "【方式2】顺序协作 - Agent链式处理\n";
echo "-----------------------------------\n";

echo "场景：需求 → 分析 → 估算 → 报价\n\n";

// Step 1: Agent分析需求
echo "Step 1: 需求分析Agent\n";
$requirement = "开发一个电商平台，包含用户系统、商品管理、订单系统";
$result1 = $aiManager->process(
    "请分析以下需求的关键功能点：{$requirement}",
    ['task_type' => 'requirement_analysis']
);
echo "  分析结果: " . substr($result1['response'] ?? $result1['message'], 0, 100) . "...\n\n";

// Step 2: 基于分析结果，工时估算Agent
echo "Step 2: 工时估算Agent (基于分析结果)\n";
$analysis = $result1['response'] ?? $result1['message'];
$result2 = $aiManager->process(
    "基于以下需求分析，估算开发工时：\n{$analysis}",
    ['task_type' => 'worktime_estimate']
);
echo "  估算结果: " . substr($result2['response'] ?? $result2['message'], 0, 100) . "...\n\n";

// Step 3: 基于工时，报价Agent
echo "Step 3: 报价Agent (基于工时估算)\n";
$estimation = $result2['response'] ?? $result2['message'];
$result3 = $aiManager->process(
    "基于以下工时估算，生成项目报价（人天单价2000元）：\n{$estimation}",
    ['task_type' => 'quotation']
);
echo "  报价结果: " . substr($result3['response'] ?? $result3['message'], 0, 100) . "...\n\n";

echo "✅ Agent链式协作完成！\n\n";

// ========================================
// 方式3: 投票/共识机制（多Agent决策）
// ========================================
echo "【方式3】多Agent投票决策\n";
echo "-----------------------------------\n";

echo "场景：3个Agent评估技术方案，取共识\n\n";

$techProposal = "使用React+Node.js开发电商平台";

// 创建3个不同视角的评估Agent
$agents = [
    'security_expert' => '安全专家',
    'performance_expert' => '性能专家',
    'cost_expert' => '成本专家'
];

$evaluations = [];

foreach ($agents as $role => $name) {
    echo "$name Agent评估中...\n";
    $result = $aiManager->process(
        "作为{$name}，评估以下技术方案的合理性（1-10分）：{$techProposal}",
        ['role' => $role]
    );
    $response = $result['response'] ?? $result['message'];
    $evaluations[$role] = $response;
    echo "  评估: " . substr($response, 0, 80) . "...\n";
}

echo "\n✅ 多Agent评估完成！可以基于共识做决策。\n\n";

// ========================================
// 方式4: 主从协作（Coordinator模式）
// ========================================
echo "【方式4】主从协作 - 协调者模式\n";
echo "-----------------------------------\n";

echo "场景：主Agent分配任务给多个子Agent\n\n";

// 主Agent：项目经理
echo "主Agent (项目经理) 分析任务...\n";
$masterResult = $aiManager->process(
    "作为项目经理，将'开发CRM系统'分解为3个子任务，每个任务指定负责人",
    ['role' => 'project_manager']
);
echo "  任务分解: " . substr($masterResult['response'] ?? $masterResult['message'], 0, 100) . "...\n\n";

// 根据主Agent的分解，启动子Agent
echo "子Agent开始执行...\n";
$subTasks = [
    ['agent' => 'backend_dev', 'task' => '后端API开发'],
    ['agent' => 'frontend_dev', 'task' => '前端界面开发'],
    ['agent' => 'qa_tester', 'task' => '质量测试']
];

foreach ($subTasks as $i => $task) {
    echo "  子Agent " . ($i + 1) . " ({$task['agent']}): {$task['task']}\n";
    // 实际场景中，这里会调用具体的Agent
}

echo "\n✅ 主从协作模式演示完成！\n\n";

// ========================================
// 方式5: 竞争机制（多Agent生成，选最优）
// ========================================
echo "【方式5】竞争机制 - 多Agent生成方案\n";
echo "-----------------------------------\n";

echo "场景：让3个Agent生成营销文案，选最好的\n\n";

$product = "智能AI客服系统";
$proposals = [];

for ($i = 1; $i <= 3; $i++) {
    echo "Agent {$i} 生成文案中...\n";
    $result = $aiManager->process(
        "为'{$product}'创作一段营销文案（风格{$i}）",
        ['agent_id' => "agent_{$i}"]
    );
    $proposals[$i] = $result['response'] ?? $result['message'];
    echo "  方案{$i}: " . substr($proposals[$i], 0, 60) . "...\n";
}

echo "\n✅ 可以让评审Agent选择最优方案！\n\n";

// ========================================
// 总结
// ========================================
echo "========================================\n";
echo "多Agent协作模式总结\n";
echo "========================================\n\n";

echo "✅ 支持的协作模式：\n";
echo "  1. 并行执行（异步队列）\n";
echo "  2. 顺序协作（管道式）\n";
echo "  3. 投票决策（共识机制）\n";
echo "  4. 主从协作（Coordinator）\n";
echo "  5. 竞争选优（多方案）\n\n";

echo "💡 实际应用场景：\n";
echo "  - 复杂项目多维度分析\n";
echo "  - 需求→设计→开发→测试流程\n";
echo "  - 技术方案评审和决策\n";
echo "  - 任务分解和并行处理\n";
echo "  - 内容生成和质量筛选\n\n";

echo "========================================\n";
