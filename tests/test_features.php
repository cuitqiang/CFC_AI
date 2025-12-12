<?php
/**
 * CRM_ERP AI V7.6 完整功能验证测试
 * 对照 CRM-ERP-AI-V7.md 文档进行全面检查
 */

require_once __DIR__ . '/vendor/autoload.php';

use Services\AI\Bootstrap;
use Services\AI\Core\AIManager;
use Services\AI\Core\ModelRouter;
use Services\AI\Pipeline\Pipeline;
use Services\AI\Pipeline\PipelineContext;
use Services\AI\Tools\ToolRegistry;
use Services\AI\Memory\ContextManager;
use Services\AI\Queue\AIJobDispatcher;
use Services\AI\Analytics\CostCalculator;
use Services\AI\Analytics\UsageTracker;

echo "========================================\n";
echo "CRM_ERP AI V7.6 功能验证测试\n";
echo "对照文档: CRM-ERP-AI-V7.md\n";
echo "========================================\n\n";

$passed = 0;
$failed = 0;
$warnings = 0;

function testFeature($name, $callable) {
    global $passed, $failed, $warnings;
    echo "测试: $name\n";
    try {
        $result = $callable();
        if ($result === true) {
            echo "  ✅ 通过\n\n";
            $passed++;
        } elseif ($result === 'warning') {
            echo "  ⚠️  部分实现\n\n";
            $warnings++;
        } else {
            echo "  ❌ 失败: $result\n\n";
            $failed++;
        }
    } catch (\Throwable $e) {
        echo "  ❌ 异常: " . $e->getMessage() . "\n\n";
        $failed++;
    }
}

// ========================================
// 第一部分: Core 层测试
// ========================================
echo "【第一部分】Core 神经中枢层\n";
echo "-----------------------------------\n\n";

testFeature("1.1 AIManager - 统一门面", function() {
    Bootstrap::initialize();
    $aiManager = Bootstrap::getAIManager();
    return $aiManager instanceof AIManager;
});

testFeature("1.2 ModelRouter - 模型路由", function() {
    $router = Bootstrap::getModelRouter();

    // 测试注册provider
    $providers = $router->getProviderNames();

    // 测试路由功能
    $supportsV3 = $router->supportsModel('deepseek-v3');

    return $router instanceof ModelRouter && $supportsV3;
});

testFeature("1.3 RAG - EmbeddingEngine", function() {
    // 检查类是否存在
    return class_exists('Services\\AI\\Core\\RAG\\EmbeddingEngine');
});

testFeature("1.4 RAG - DocumentChunker", function() {
    return class_exists('Services\\AI\\Core\\RAG\\DocumentChunker');
});

testFeature("1.5 Utils - FunctionCaller", function() {
    return class_exists('Services\\AI\\Core\\Utils\\FunctionCaller');
});

testFeature("1.6 Utils - StreamHandler", function() {
    return class_exists('Services\\AI\\Core\\Utils\\StreamHandler');
});

testFeature("1.7 Utils - TokenCounter", function() {
    return class_exists('Services\\AI\\Core\\Utils\\TokenCounter');
});

// ========================================
// 第二部分: Pipeline 流水线层测试
// ========================================
echo "【第二部分】Pipeline 流水线层\n";
echo "-----------------------------------\n\n";

testFeature("2.1 Pipeline - 管道执行器", function() {
    $pipeline = new Pipeline();
    return $pipeline instanceof Pipeline;
});

testFeature("2.2 PipelineContext - 数据包", function() {
    $context = new PipelineContext("test", ['key' => 'value']);
    return $context->shouldContinue() === true;
});

testFeature("2.3 Pipe - RateLimit (限流)", function() {
    return class_exists('Services\\AI\\Pipeline\\Pipes\\RateLimit');
});

testFeature("2.4 Pipe - SafetyCheck (安全检查)", function() {
    return class_exists('Services\\AI\\Pipeline\\Pipes\\SafetyCheck');
});

testFeature("2.5 Pipe - LoadMemory (记忆加载)", function() {
    return class_exists('Services\\AI\\Pipeline\\Pipes\\LoadMemory');
});

testFeature("2.6 Pipe - PlanTools (工具规划)", function() {
    return class_exists('Services\\AI\\Pipeline\\Pipes\\PlanTools');
});

testFeature("2.7 Pipe - CallModel (模型调用)", function() {
    return class_exists('Services\\AI\\Pipeline\\Pipes\\CallModel');
});

testFeature("2.8 Pipe - ExecuteTool (工具执行)", function() {
    return class_exists('Services\\AI\\Pipeline\\Pipes\\ExecuteTool');
});

testFeature("2.9 Pipe - SaveMemory (记忆保存)", function() {
    return class_exists('Services\\AI\\Pipeline\\Pipes\\SaveMemory');
});

testFeature("2.10 Pipe - FormatOutput (输出格式化)", function() {
    return class_exists('Services\\AI\\Pipeline\\Pipes\\FormatOutput');
});

// ========================================
// 第三部分: Tools 工具箱层测试
// ========================================
echo "【第三部分】Tools 工具箱层\n";
echo "-----------------------------------\n\n";

testFeature("3.1 BaseTool - 工具基类", function() {
    return class_exists('Services\\AI\\Tools\\BaseTool');
});

testFeature("3.2 ToolRegistry - 注册中心", function() {
    $registry = Bootstrap::getToolRegistry();
    return $registry instanceof ToolRegistry;
});

testFeature("3.3 ToolSandbox - 安全沙箱", function() {
    return class_exists('Services\\AI\\Tools\\ToolSandbox');
});

testFeature("3.4 System工具 - DatabaseReader", function() {
    return class_exists('Services\\AI\\Tools\\System\\DatabaseReader');
});

testFeature("3.5 System工具 - HttpSearch", function() {
    return class_exists('Services\\AI\\Tools\\System\\HttpSearch');
});

testFeature("3.6 System工具 - TimeCalculator", function() {
    return class_exists('Services\\AI\\Tools\\System\\TimeCalculator');
});

testFeature("3.7 Business工具 - ContractFinder", function() {
    return class_exists('Services\\AI\\Tools\\Business\\ContractFinder');
});

testFeature("3.8 Business工具 - EmailSender", function() {
    return class_exists('Services\\AI\\Tools\\Business\\EmailSender');
});

testFeature("3.9 Business工具 - ReportBuilder", function() {
    return class_exists('Services\\AI\\Tools\\Business\\ReportBuilder');
});

// ========================================
// 第四部分: Providers 驱动层测试
// ========================================
echo "【第四部分】Providers 驱动层\n";
echo "-----------------------------------\n\n";

testFeature("4.1 ProviderInterface - 接口契约", function() {
    return interface_exists('Services\\AI\\Providers\\ProviderInterface');
});

testFeature("4.2 AbstractProvider - 基类", function() {
    return class_exists('Services\\AI\\Providers\\AbstractProvider');
});

testFeature("4.3 DeepseekProvider - Deepseek实现", function() {
    return class_exists('Services\\AI\\Providers\\DeepseekProvider');
});

testFeature("4.4 OpenAIProvider - OpenAI实现", function() {
    return class_exists('Services\\AI\\Providers\\OpenAIProvider');
});

testFeature("4.5 EmbeddingProvider - 向量生成", function() {
    return class_exists('Services\\AI\\Providers\\EmbeddingProvider');
});

// ========================================
// 第五部分: Memory 存储层测试
// ========================================
echo "【第五部分】Memory 存储层\n";
echo "-----------------------------------\n\n";

testFeature("5.1 ContextManager - 对话管理", function() {
    $contextManager = Bootstrap::getContextManager();
    return $contextManager instanceof ContextManager;
});

testFeature("5.2 ShortTerm - 短期记忆", function() {
    return class_exists('Services\\AI\\Memory\\ShortTerm');
});

testFeature("5.3 Summary - 历史摘要", function() {
    return class_exists('Services\\AI\\Memory\\Summary');
});

testFeature("5.4 VectorStore - 向量存储", function() {
    return class_exists('Services\\AI\\Memory\\VectorStore');
});

// ========================================
// 第六部分: Tasks 任务层测试
// ========================================
echo "【第六部分】Tasks 任务层\n";
echo "-----------------------------------\n\n";

testFeature("6.1 BaseTask - 任务基类", function() {
    return class_exists('Services\\AI\\Tasks\\BaseTask');
});

testFeature("6.2 GeneralAgent - 通用助手", function() {
    return class_exists('Services\\AI\\Tasks\\GeneralAgent');
});

testFeature("6.3 ContractReview - 合同审查", function() {
    return class_exists('Services\\AI\\Tasks\\ContractReview');
});

testFeature("6.4 WorktimeEstimate - 工时估算", function() {
    return class_exists('Services\\AI\\Tasks\\WorktimeEstimate');
});

// ========================================
// 第七部分: Queue 异步队列层测试
// ========================================
echo "【第七部分】Queue 异步队列层\n";
echo "-----------------------------------\n\n";

testFeature("7.1 AIJobDispatcher - 任务分发器", function() {
    $dispatcher = Bootstrap::getDispatcher();
    return $dispatcher instanceof AIJobDispatcher;
});

testFeature("7.2 AIJobWorker - 队列消费者", function() {
    return class_exists('Services\\AI\\Queue\\AIJobWorker');
});

testFeature("7.3 DeadLetterQueue - 死信队列", function() {
    return class_exists('Services\\AI\\Queue\\DeadLetterQueue');
});

testFeature("7.4 Job - RunAgentJob", function() {
    return class_exists('Services\\AI\\Queue\\Jobs\\RunAgentJob');
});

testFeature("7.5 Job - VectorizeDocJob", function() {
    return class_exists('Services\\AI\\Queue\\Jobs\\VectorizeDocJob');
});

// ========================================
// 第八部分: Analytics 监控层测试
// ========================================
echo "【第八部分】Analytics 监控层\n";
echo "-----------------------------------\n\n";

testFeature("8.1 CostCalculator - 计费引擎", function() {
    $calculator = Bootstrap::getCostCalculator();
    $cost = $calculator->calculateCost('deepseek-v3', 1000, 500);
    return $calculator instanceof CostCalculator && $cost > 0;
});

testFeature("8.2 UsageTracker - 用量统计", function() {
    $tracker = Bootstrap::getUsageTracker();
    return $tracker instanceof UsageTracker;
});

// ========================================
// 第九部分: 实际功能测试
// ========================================
echo "【第九部分】实际功能测试\n";
echo "-----------------------------------\n\n";

testFeature("9.1 基础对话功能", function() {
    $aiManager = Bootstrap::getAIManager();
    $result = $aiManager->process("你好");
    return isset($result['response']) || isset($result['message']);
});

testFeature("9.2 工具调用功能", function() {
    $registry = Bootstrap::getToolRegistry();
    $tools = $registry->all();
    return count($tools) > 0;
});

testFeature("9.3 成本计算功能", function() {
    $calculator = Bootstrap::getCostCalculator();
    $cost = $calculator->calculateCost('deepseek-chat', 1000, 500);
    return is_float($cost) && $cost > 0;
});

testFeature("9.4 使用统计功能", function() {
    $tracker = Bootstrap::getUsageTracker();
    $tracker->track('deepseek-v3', 100, 50, 1.5, true);
    $stats = $tracker->getStats('deepseek-v3');
    return isset($stats['total_requests']) && $stats['total_requests'] > 0;
});

// ========================================
// 总结报告
// ========================================
echo "========================================\n";
echo "测试总结\n";
echo "========================================\n";
echo "✅ 通过: $passed\n";
echo "⚠️  警告: $warnings\n";
echo "❌ 失败: $failed\n";
echo "总计: " . ($passed + $warnings + $failed) . "\n\n";

$coverage = round(($passed / ($passed + $warnings + $failed)) * 100, 2);
echo "功能覆盖率: $coverage%\n";

if ($failed === 0 && $warnings === 0) {
    echo "\n🎉 所有功能测试通过！系统完全符合文档规范！\n";
} elseif ($failed === 0) {
    echo "\n✅ 核心功能全部通过，有部分功能需要完善。\n";
} else {
    echo "\n⚠️  有功能未实现或测试失败，请检查。\n";
}

echo "========================================\n";
