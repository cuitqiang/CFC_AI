<?php
declare(strict_types=1);

/**
 * 手动加载器 - 用于在没有 Composer 的情况下测试
 */

// 设置基础路径
define('BASE_PATH', __DIR__);
define('AI_PATH', BASE_PATH . '/src/Services/AI');

// 简单的自动加载器
spl_autoload_register(function ($class) {
    // 将命名空间转换为文件路径
    // Services\AI\Core\AIManager -> src/Services/AI/Core/AIManager.php

    $prefix = 'Services\\AI\\';
    $base_dir = AI_PATH . '/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

echo "=== CRM_AI_V7.6 系统测试 ===\n\n";

// 加载配置
require_once AI_PATH . '/Config.php';

try {
    // 测试配置加载
    echo "1. 测试配置加载...\n";
    Services\AI\Config::load(BASE_PATH . '/.env');
    echo "   ✅ 配置加载成功\n\n";

    // 测试 PipelineContext
    echo "2. 测试 PipelineContext...\n";
    $context = new Services\AI\Pipeline\PipelineContext(
        "你好，世界",
        ['user_id' => 'test_user']
    );
    echo "   ✅ PipelineContext 创建成功\n";
    echo "   用户输入: {$context->getUserInput()}\n\n";

    // 测试 Pipeline
    echo "3. 测试 Pipeline...\n";
    $pipeline = new Services\AI\Pipeline\Pipeline();

    // 添加一个简单的测试 Pipe
    $pipeline->pipe(function($ctx) {
        echo "   - Pipe 执行中...\n";
        $ctx->setMetadata('test', 'success');
        return $ctx;
    });

    $result = $pipeline->process($context);
    echo "   ✅ Pipeline 执行成功\n";
    echo "   测试元数据: " . $result->getMetadataValue('test') . "\n\n";

    // 测试工具注册
    echo "4. 测试 ToolRegistry...\n";
    $registry = new Services\AI\Tools\ToolRegistry();
    echo "   ✅ ToolRegistry 创建成功\n";
    echo "   工具数量: " . $registry->count() . "\n\n";

    // 测试时间计算工具
    echo "5. 测试 TimeCalculator 工具...\n";
    $timeTool = new Services\AI\Tools\System\TimeCalculator();
    echo "   工具名称: " . $timeTool->getName() . "\n";
    echo "   工具描述: " . $timeTool->getDescription() . "\n";

    $timeResult = $timeTool->execute([
        'operation' => 'current',
    ]);

    if ($timeResult['success']) {
        echo "   ✅ 时间计算成功\n";
        echo "   当前时间: " . $timeResult['data']['formatted'] . "\n\n";
    }

    // 测试成本计算器
    echo "6. 测试 CostCalculator...\n";
    $costCalc = new Services\AI\Analytics\CostCalculator();
    $cost = $costCalc->calculateCost('deepseek-chat', 1000, 500);
    echo "   ✅ 成本计算成功\n";
    echo "   模型: deepseek-chat\n";
    echo "   输入: 1000 tokens, 输出: 500 tokens\n";
    echo "   成本: $" . number_format($cost, 6) . "\n\n";

    // 测试使用追踪
    echo "7. 测试 UsageTracker...\n";
    $tracker = new Services\AI\Analytics\UsageTracker();
    $tracker->track('deepseek-chat', 1000, 500, 1.2, true);
    $tracker->track('deepseek-chat', 800, 400, 1.0, true);

    $stats = $tracker->getStats('deepseek-chat');
    echo "   ✅ 使用追踪成功\n";
    echo "   总请求: " . $stats['total_requests'] . "\n";
    echo "   成功率: " . number_format($stats['success_rate'], 2) . "%\n";
    echo "   平均延迟: " . number_format($stats['avg_latency'], 3) . "s\n\n";

    // 测试队列调度器
    echo "8. 测试 AIJobDispatcher...\n";
    $dispatcher = new Services\AI\Queue\AIJobDispatcher();
    $dispatcher->registerQueue('test');
    echo "   ✅ 队列调度器创建成功\n";

    $queueStats = $dispatcher->getStats('test');
    echo "   待处理: " . $queueStats['pending'] . "\n";
    echo "   已完成: " . $queueStats['completed'] . "\n\n";

    // 测试记忆系统
    echo "9. 测试 Memory 系统...\n";
    $shortTerm = new Services\AI\Memory\ShortTerm();
    $shortTerm->add('test_user', [
        ['role' => 'user', 'content' => '你好'],
        ['role' => 'assistant', 'content' => '你好！有什么可以帮助你的？'],
    ], 'session_001');

    $messages = $shortTerm->get('test_user', 'session_001');
    echo "   ✅ ShortTerm 记忆测试成功\n";
    echo "   存储消息数: " . count($messages) . "\n\n";

    echo "=== ✅ 所有测试通过！===\n\n";

    echo "系统组件验证：\n";
    echo "├─ Pipeline 系统 ✅\n";
    echo "├─ Tools 工具系统 ✅\n";
    echo "├─ Analytics 分析 ✅\n";
    echo "├─ Queue 队列 ✅\n";
    echo "└─ Memory 记忆 ✅\n\n";

    echo "下一步：\n";
    echo "1. 安装 PHP 8.3+ 和 Composer\n";
    echo "2. 运行 'composer install'\n";
    echo "3. 配置 .env 文件中的 API Keys\n";
    echo "4. 运行 'php examples/00_quickstart.php'\n\n";

} catch (\Throwable $e) {
    echo "\n❌ 错误: " . $e->getMessage() . "\n";
    echo "文件: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\n堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
}
