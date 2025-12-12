<?php
declare(strict_types=1);

/**
 * 队列和异步处理示例
 * 演示如何使用队列系统处理任务
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Services\AI\Config;
use Services\AI\Core\AIManager;
use Services\AI\Core\ModelRouter;
use Services\AI\Providers\DeepseekProvider;
use Services\AI\Queue\AIJobDispatcher;
use Services\AI\Queue\AIJobWorker;
use Services\AI\Queue\DeadLetterQueue;
use Services\AI\Queue\Jobs\RunAgentJob;
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

echo "=== AI Agent 队列处理示例 ===\n\n";

// 1. 创建调度器和工作器
$dispatcher = new AIJobDispatcher();
$deadLetterQueue = new DeadLetterQueue();
$worker = new AIJobWorker($dispatcher, $deadLetterQueue);

// 2. 注册队列
$dispatcher->registerQueue('default');
$dispatcher->registerQueue('high_priority', ['priority' => 10]);

echo "--- 分发任务到队列 ---\n\n";

// 3. 创建任务
$questions = [
    "什么是人工智能？",
    "解释一下机器学习和深度学习的区别",
    "Python 适合做什么？",
];

$jobIds = [];

foreach ($questions as $i => $question) {
    $job = new RunAgentJob(
        $aiManager,
        $question,
        [
            'user_id' => 'user_004',
            'callback' => function ($result) use ($question) {
                echo "[回调] 问题「{$question}」处理完成\n";
            }
        ]
    );

    $jobId = $dispatcher->dispatch($job);
    $jobIds[] = $jobId;

    echo "任务 {$i}: {$question} (ID: {$jobId})\n";
}

echo "\n已分发 " . count($jobIds) . " 个任务\n\n";

// 4. 查看队列统计
$stats = $dispatcher->getStats();
echo "--- 队列统计 ---\n";
echo "待处理: {$stats['default']['pending']}\n";
echo "处理中: {$stats['default']['processing']}\n";
echo "已完成: {$stats['default']['completed']}\n";
echo "失败: {$stats['default']['failed']}\n\n";

// 5. 处理任务
echo "--- 开始处理任务 ---\n\n";

$processedCount = 0;
while ($worker->work()) {
    $processedCount++;
    echo "已处理 {$processedCount} 个任务\n";

    // 处理所有任务后退出
    if ($processedCount >= count($jobIds)) {
        break;
    }
}

echo "\n";

// 6. 查看任务状态
echo "--- 任务状态 ---\n\n";

foreach ($jobIds as $i => $jobId) {
    $status = $dispatcher->getJobStatus($jobId);
    echo "任务 {$i}: {$status['status']}\n";
}

echo "\n";

// 7. 查看最终统计
$finalStats = $dispatcher->getStats();
echo "--- 最终统计 ---\n";
echo "总任务: {$finalStats['default']['total']}\n";
echo "已完成: {$finalStats['default']['completed']}\n";
echo "失败: {$finalStats['default']['failed']}\n\n";

// 8. 死信队列统计
$dlqStats = $deadLetterQueue->getStats();
echo "--- 死信队列 ---\n";
echo "失败任务: {$dlqStats['total']}\n\n";

echo "=== 示例结束 ===\n";
