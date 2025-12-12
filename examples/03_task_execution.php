<?php
declare(strict_types=1);

/**
 * 任务执行示例
 * 演示如何使用专门的任务类
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Services\AI\Config;
use Services\AI\Core\AIManager;
use Services\AI\Core\ModelRouter;
use Services\AI\Providers\DeepseekProvider;
use Services\AI\Tasks\GeneralAgent;
use Services\AI\Tasks\ContractReview;
use Services\AI\Tasks\WorktimeEstimate;
use Services\AI\Tools\ToolRegistry;
use Services\AI\Memory\ContextManager;
use Services\AI\Memory\ShortTerm;
use Services\AI\Memory\Summary;
use Services\AI\Memory\VectorStore;

// 加载配置
Config::load(__DIR__ . '/../.env');

// 初始化系统
$deepseekProvider = new DeepseekProvider(
    Config::get('deepseek.api_key'),
    Config::get('deepseek.base_url')
);

$modelRouter = new ModelRouter();
$modelRouter->register('deepseek', $deepseekProvider);

$contextManager = new ContextManager(
    new ShortTerm(),
    new Summary(),
    new VectorStore()
);

$aiManager = new AIManager(
    $modelRouter,
    new ToolRegistry(),
    $contextManager
);

echo "=== AI Agent 任务执行示例 ===\n\n";

// 示例 1: 通用代理
echo "--- 示例 1: 通用代理 ---\n\n";

$generalAgent = new GeneralAgent($aiManager);

$result1 = $generalAgent->execute([
    'query' => 'PHP 8.3 有哪些新特性？',
    'user_id' => 'user_003',
]);

if ($result1['success']) {
    echo "回答: {$result1['data']['answer']}\n\n";
} else {
    echo "错误: {$result1['error']}\n\n";
}

// 示例 2: 合同审查
echo "--- 示例 2: 合同审查 ---\n\n";

$contractReview = new ContractReview($aiManager);

$contractText = <<<CONTRACT
甲方：XXX公司
乙方：YYY公司

第一条：合作内容
甲方委托乙方开发软件系统。

第二条：费用
项目总费用为 100 万元，乙方需在项目启动前支付全款。

第三条：交付
乙方应在收到款项后的 30 天内完成项目交付。

第四条：违约责任
如甲方延期付款，无需承担任何责任。
如乙方延期交付，需支付项目总费用 50% 的违约金。
CONTRACT;

$result2 = $contractReview->execute([
    'contract_text' => $contractText,
    'contract_type' => 'service',
    'user_id' => 'user_003',
]);

if ($result2['success']) {
    echo "风险等级: {$result2['data']['risk_level']}\n";
    echo "\n主要风险点:\n";
    foreach ($result2['data']['risk_points'] as $i => $risk) {
        echo ($i + 1) . ". {$risk}\n";
    }
    echo "\n不合理条款:\n";
    foreach ($result2['data']['unfair_clauses'] as $i => $clause) {
        echo ($i + 1) . ". {$clause}\n";
    }
    echo "\n";
} else {
    echo "错误: {$result2['error']}\n\n";
}

// 示例 3: 工时估算
echo "--- 示例 3: 工时估算 ---\n\n";

$worktimeEstimate = new WorktimeEstimate($aiManager);

$projectDescription = <<<PROJECT
项目名称：电商平台移动端 APP

功能需求：
1. 用户注册/登录（手机号+验证码）
2. 商品浏览和搜索
3. 购物车功能
4. 订单管理
5. 在线支付（支付宝、微信）
6. 个人中心（订单历史、收货地址）
7. 推送通知

技术栈：
- 前端：React Native
- 后端：已有 API
- 需要对接现有后端系统
PROJECT;

$result3 = $worktimeEstimate->execute([
    'project_description' => $projectDescription,
    'team_size' => 3,
    'complexity' => 'medium',
    'user_id' => 'user_003',
]);

if ($result3['success']) {
    echo "总工时: {$result3['data']['total_hours']} 小时\n";
    echo "开发工时: {$result3['data']['development_hours']} 小时\n";
    echo "测试工时: {$result3['data']['testing_hours']} 小时\n";
    echo "预计工期: {$result3['data']['duration_days']} 天\n";
    echo "\n任务分解:\n";
    foreach ($result3['data']['tasks'] as $i => $task) {
        echo ($i + 1) . ". {$task['name']} - {$task['hours']} 小时\n";
    }
    echo "\n";
} else {
    echo "错误: {$result3['error']}\n\n";
}

echo "=== 示例结束 ===\n";
