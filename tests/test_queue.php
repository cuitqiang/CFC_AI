<?php
/**
 * 异步队列系统完整测试
 * 测试 AIJobDispatcher、AIJobWorker、DeadLetterQueue
 */

require_once __DIR__ . '/vendor/autoload.php';

use Services\AI\Bootstrap;
use Services\AI\Queue\AIJobDispatcher;
use Services\AI\Queue\AIJobWorker;
use Services\AI\Queue\DeadLetterQueue;
use Services\AI\Queue\Jobs\RunAgentJob;

echo "========================================\n";
echo "异步队列系统完整测试\n";
echo "========================================\n\n";

Bootstrap::initialize();
$aiManager = Bootstrap::getAIManager();

// ========================================
// 测试1: 任务分发基础功能
// ========================================
echo "【测试1】任务分发基础功能\n";
echo "-----------------------------------\n";

$dispatcher = new AIJobDispatcher();

// 注册队列
echo "注册队列...\n";
$dispatcher->registerQueue('default');
$dispatcher->registerQueue('high-priority', ['priority' => 10]);
$dispatcher->registerQueue('low-priority', ['priority' => 1]);
echo "  ✓ 已注册 3 个队列\n\n";

// 创建测试任务
$job1 = new RunAgentJob($aiManager, "分析客户需求", ['user_id' => 'user_001']);
$job2 = new RunAgentJob($aiManager, "生成报告", ['user_id' => 'user_002']);
$job3 = new RunAgentJob($aiManager, "数据统计", ['user_id' => 'user_003']);

echo "分发任务到队列...\n";
$jobId1 = $dispatcher->dispatch($job1);
$jobId2 = $dispatcher->dispatch($job2);
$jobId3 = $dispatcher->dispatch($job3);

echo "  ✓ 任务1: {$jobId1}\n";
echo "  ✓ 任务2: {$jobId2}\n";
echo "  ✓ 任务3: {$jobId3}\n\n";

echo "✅ 任务分发测试完成\n\n";

// ========================================
// 测试2: 任务状态查询
// ========================================
echo "【测试2】任务状态查询\n";
echo "-----------------------------------\n";

echo "查询任务状态...\n";
$status1 = $dispatcher->getJobStatus($jobId1);
echo "  任务1 状态: {$status1['status']}\n";
echo "  任务1 优先级: {$status1['priority']}\n";
echo "  任务1 尝试次数: {$status1['attempts']}\n\n";

echo "✅ 状态查询测试完成\n\n";

// ========================================
// 测试3: 队列统计
// ========================================
echo "【测试3】队列统计\n";
echo "-----------------------------------\n";

echo "获取队列统计信息...\n";
$stats = $dispatcher->getStats();

foreach ($stats as $queueName => $stat) {
    echo "\n  队列: {$queueName}\n";
    echo "    总任务数: {$stat['total']}\n";
    echo "    待处理: {$stat['pending']}\n";
    echo "    处理中: {$stat['processing']}\n";
    echo "    已完成: {$stat['completed']}\n";
    echo "    失败: {$stat['failed']}\n";
}

echo "\n✅ 队列统计测试完成\n\n";

// ========================================
// 测试4: 批量分发
// ========================================
echo "【测试4】批量分发\n";
echo "-----------------------------------\n";

$batchJobs = [];
for ($i = 1; $i <= 5; $i++) {
    $batchJobs[] = new RunAgentJob(
        $aiManager,
        "批量任务 {$i}",
        ['batch_id' => $i]
    );
}

echo "批量分发 5 个任务...\n";
$batchJobIds = $dispatcher->dispatchBatch($batchJobs);
echo "  ✓ 已分发 " . count($batchJobIds) . " 个任务\n";

foreach ($batchJobIds as $i => $jobId) {
    echo "    " . ($i + 1) . ". {$jobId}\n";
}

echo "\n✅ 批量分发测试完成\n\n";

// ========================================
// 测试5: 延迟任务
// ========================================
echo "【测试5】延迟任务\n";
echo "-----------------------------------\n";

$delayedJob = new RunAgentJob($aiManager, "延迟执行的任务", ['delayed' => true]);

echo "分发延迟任务（延迟 3 秒）...\n";
$delayedJobId = $dispatcher->dispatchDelayed($delayedJob, 3);
echo "  ✓ 任务ID: {$delayedJobId}\n";

$delayedStatus = $dispatcher->getJobStatus($delayedJobId);
echo "  状态: {$delayedStatus['status']}\n";
echo "  执行时间: " . date('H:i:s', $delayedStatus['execute_at']) . "\n\n";

echo "✅ 延迟任务测试完成\n\n";

// ========================================
// 测试6: 优先级队列
// ========================================
echo "【测试6】优先级队列\n";
echo "-----------------------------------\n";

$dispatcher2 = new AIJobDispatcher();
$dispatcher2->registerQueue('default');

echo "创建不同优先级的任务...\n";
$lowPriorityJob = new RunAgentJob($aiManager, "低优先级", []);
$normalJob = new RunAgentJob($aiManager, "正常优先级", []);
$highPriorityJob = new RunAgentJob($aiManager, "高优先级", []);

$lowId = $dispatcher2->dispatch($lowPriorityJob, 'default', 1);
$normalId = $dispatcher2->dispatch($normalJob, 'default', 5);
$highId = $dispatcher2->dispatch($highPriorityJob, 'default', 10);

echo "  低优先级 (1): {$lowId}\n";
echo "  正常 (5): {$normalId}\n";
echo "  高优先级 (10): {$highId}\n\n";

echo "获取下一个任务（应该是高优先级）...\n";
$nextJob = $dispatcher2->getNextJob();
echo "  下一个任务优先级: {$nextJob['priority']}\n";
echo "  ✓ " . ($nextJob['priority'] === 10 ? '正确' : '错误') . "\n\n";

echo "✅ 优先级测试完成\n\n";

// ========================================
// 测试7: Worker 处理任务
// ========================================
echo "【测试7】Worker 处理任务\n";
echo "-----------------------------------\n";

// 创建简单的测试任务类
class SimpleJob
{
    private string $message;

    public function __construct(string $message)
    {
        $this->message = $message;
    }

    public function handle(): array
    {
        echo "    执行任务: {$this->message}\n";
        return ['success' => true, 'message' => $this->message];
    }
}

$dispatcher3 = new AIJobDispatcher();
$dispatcher3->registerQueue('test');

echo "创建 3 个简单任务...\n";
$simpleJob1 = new SimpleJob("任务A");
$simpleJob2 = new SimpleJob("任务B");
$simpleJob3 = new SimpleJob("任务C");

$id1 = $dispatcher3->dispatch($simpleJob1, 'test');
$id2 = $dispatcher3->dispatch($simpleJob2, 'test');
$id3 = $dispatcher3->dispatch($simpleJob3, 'test');

echo "\n创建 Worker 并处理任务...\n";
$worker = new AIJobWorker($dispatcher3);

echo "\n  处理任务1:\n";
$worker->work('test');

echo "\n  处理任务2:\n";
$worker->work('test');

echo "\n  处理任务3:\n";
$worker->work('test');

echo "\n检查任务状态...\n";
$finalStatus1 = $dispatcher3->getJobStatus($id1);
$finalStatus2 = $dispatcher3->getJobStatus($id2);
$finalStatus3 = $dispatcher3->getJobStatus($id3);

echo "  任务1: {$finalStatus1['status']}\n";
echo "  任务2: {$finalStatus2['status']}\n";
echo "  任务3: {$finalStatus3['status']}\n\n";

echo "✅ Worker 处理测试完成\n\n";

// ========================================
// 测试8: 失败重试机制
// ========================================
echo "【测试8】失败重试机制\n";
echo "-----------------------------------\n";

class FailingJob
{
    private static int $attemptCount = 0;

    public function handle(): array
    {
        self::$attemptCount++;
        echo "    尝试 #" . self::$attemptCount . "\n";

        if (self::$attemptCount < 3) {
            throw new \Exception("模拟失败（尝试 " . self::$attemptCount . "/3）");
        }

        echo "    ✓ 第3次尝试成功！\n";
        return ['success' => true];
    }
}

$dispatcher4 = new AIJobDispatcher();
$dispatcher4->registerQueue('retry-test', ['max_retries' => 3]);

echo "创建会失败的任务（前2次失败，第3次成功）...\n";
$failJob = new FailingJob();
$failJobId = $dispatcher4->dispatch($failJob, 'retry-test');

$deadLetterQueue = new DeadLetterQueue();
$worker2 = new AIJobWorker($dispatcher4, $deadLetterQueue);

echo "\n开始处理（会自动重试）...\n";
for ($i = 0; $i < 3; $i++) {
    echo "\n  第 " . ($i + 1) . " 轮处理:\n";
    $worker2->work('retry-test');
}

$retryStatus = $dispatcher4->getJobStatus($failJobId);
echo "\n最终状态: {$retryStatus['status']}\n";
echo "尝试次数: {$retryStatus['attempts']}\n\n";

echo "✅ 失败重试测试完成\n\n";

// ========================================
// 测试9: 死信队列
// ========================================
echo "【测试9】死信队列\n";
echo "-----------------------------------\n";

class AlwaysFailJob
{
    public function handle(): array
    {
        throw new \Exception("这个任务总是失败");
    }
}

$dispatcher5 = new AIJobDispatcher();
$dispatcher5->registerQueue('fail-queue', ['max_retries' => 2]);

$dlq = new DeadLetterQueue();
$worker3 = new AIJobWorker($dispatcher5, $dlq);

echo "创建总是失败的任务...\n";
$alwaysFailJob = new AlwaysFailJob();
$failId = $dispatcher5->dispatch($alwaysFailJob, 'fail-queue');

echo "\n处理任务（会失败并进入死信队列）...\n";
for ($i = 0; $i < 3; $i++) {
    echo "  尝试 " . ($i + 1) . "...\n";
    $worker3->work('fail-queue');
}

$deadJobs = $dlq->getAll();
echo "\n死信队列中的任务数: " . count($deadJobs) . "\n";

if (!empty($deadJobs)) {
    $deadJob = $deadJobs[0];
    echo "  任务ID: {$deadJob['job_id']}\n";
    echo "  错误: {$deadJob['error']}\n";
    echo "  尝试次数: {$deadJob['attempts']}\n";
}

echo "\n死信队列统计:\n";
$dlqStats = $dlq->getStats();
echo "  总数: {$dlqStats['total']}\n";

echo "\n✅ 死信队列测试完成\n\n";

// ========================================
// 测试10: 死信队列重试
// ========================================
echo "【测试10】死信队列重试\n";
echo "-----------------------------------\n";

if (!empty($deadJobs)) {
    $deadJobId = $deadJobs[0]['job_id'];

    echo "从死信队列重试任务...\n";
    echo "  任务ID: {$deadJobId}\n";

    $retried = $dlq->retry($deadJobId, $dispatcher5);
    echo "  重试结果: " . ($retried ? '✓ 成功' : '✗ 失败') . "\n";

    $dlqAfterRetry = $dlq->getAll();
    echo "  死信队列剩余: " . count($dlqAfterRetry) . " 个\n";
}

echo "\n✅ 死信队列重试测试完成\n\n";

// ========================================
// 测试11: 任务清理
// ========================================
echo "【测试11】任务清理\n";
echo "-----------------------------------\n";

$dispatcher6 = new AIJobDispatcher();
$dispatcher6->registerQueue('cleanup-test');

echo "创建并完成一些任务...\n";
for ($i = 1; $i <= 3; $i++) {
    $job = new SimpleJob("清理测试 {$i}");
    $jobId = $dispatcher6->dispatch($job, 'cleanup-test');

    // 标记为完成
    $dispatcher6->updateJobStatus($jobId, 'completed');
}

$beforeCleanup = $dispatcher6->getStats('cleanup-test');
echo "  清理前: {$beforeCleanup['total']} 个任务\n";

echo "\n执行清理（清理1秒前的任务）...\n";
$dispatcher6->cleanup(time() + 1);

$afterCleanup = $dispatcher6->getStats('cleanup-test');
echo "  清理后: {$afterCleanup['total']} 个任务\n\n";

echo "✅ 任务清理测试完成\n\n";

// ========================================
// 测试12: 完整工作流
// ========================================
echo "【测试12】完整工作流演示\n";
echo "-----------------------------------\n";

echo "场景: 多任务并发处理\n\n";

$dispatcher7 = new AIJobDispatcher();
$dispatcher7->registerQueue('workflow');

class WorkflowJob
{
    private string $taskName;
    private int $duration;

    public function __construct(string $taskName, int $duration = 1)
    {
        $this->taskName = $taskName;
        $this->duration = $duration;
    }

    public function handle(): array
    {
        echo "      执行: {$this->taskName}\n";
        // 模拟处理时间（实际中会是真实的AI处理）
        // sleep($this->duration);
        return ['success' => true, 'task' => $this->taskName];
    }
}

echo "Step 1: 分发 5 个任务\n";
$workflowJobs = [
    new WorkflowJob("数据收集", 1),
    new WorkflowJob("数据清洗", 2),
    new WorkflowJob("数据分析", 3),
    new WorkflowJob("生成报告", 2),
    new WorkflowJob("发送邮件", 1),
];

$workflowIds = $dispatcher7->dispatchBatch($workflowJobs, 'workflow');
echo "  ✓ 已分发 " . count($workflowIds) . " 个任务\n\n";

echo "Step 2: Worker 处理所有任务\n";
$worker4 = new AIJobWorker($dispatcher7);

foreach ($workflowIds as $i => $id) {
    echo "  处理任务 " . ($i + 1) . ":\n";
    $worker4->work('workflow');
}

echo "\nStep 3: 检查最终状态\n";
$finalStats = $dispatcher7->getStats('workflow');
echo "  总任务: {$finalStats['total']}\n";
echo "  已完成: {$finalStats['completed']}\n";
echo "  失败: {$finalStats['failed']}\n\n";

echo "✅ 完整工作流演示完成\n\n";

// ========================================
// 总结
// ========================================
echo "========================================\n";
echo "异步队列测试总结\n";
echo "========================================\n\n";

echo "✅ 已测试的功能:\n";
echo "  1. 任务分发 (dispatch)\n";
echo "  2. 任务状态查询 (getJobStatus)\n";
echo "  3. 队列统计 (getStats)\n";
echo "  4. 批量分发 (dispatchBatch)\n";
echo "  5. 延迟任务 (dispatchDelayed)\n";
echo "  6. 优先级队列\n";
echo "  7. Worker 任务处理 (work)\n";
echo "  8. 失败重试机制\n";
echo "  9. 死信队列 (DeadLetterQueue)\n";
echo "  10. 死信队列重试 (retry)\n";
echo "  11. 任务清理 (cleanup)\n";
echo "  12. 完整工作流\n\n";

echo "📊 队列系统核心能力:\n";
echo "  ✅ 异步任务分发\n";
echo "  ✅ 任务优先级控制\n";
echo "  ✅ 批量任务处理\n";
echo "  ✅ 延迟任务执行\n";
echo "  ✅ 自动失败重试\n";
echo "  ✅ 死信队列管理\n";
echo "  ✅ 任务状态追踪\n";
echo "  ✅ 队列统计分析\n\n";

echo "🏗️ 队列系统架构:\n";
echo "  ┌─────────────────────────────┐\n";
echo "  │    AIJobDispatcher          │\n";
echo "  │  (任务分发器 - 任务入口)     │\n";
echo "  └──────────┬──────────────────┘\n";
echo "             │\n";
echo "     ┌───────┴────────┐\n";
echo "     ▼                ▼\n";
echo "  Queue1          Queue2\n";
echo "  (default)       (high-priority)\n";
echo "     │                │\n";
echo "     └────────┬───────┘\n";
echo "              ▼\n";
echo "      AIJobWorker\n";
echo "      (任务消费者)\n";
echo "              │\n";
echo "     ┌────────┴────────┐\n";
echo "     ▼                 ▼\n";
echo "  Success       Failed (max retries)\n";
echo "  (completed)         │\n";
echo "                      ▼\n";
echo "              DeadLetterQueue\n";
echo "              (死信队列)\n\n";

echo "🎯 应用场景:\n";
echo "  • 异步 AI 处理（避免阻塞）\n";
echo "  • 批量文档向量化\n";
echo "  • 定时任务调度\n";
echo "  • 多 Agent 并发执行\n";
echo "  • 失败任务自动重试\n\n";

echo "💡 使用示例:\n";
echo "```php\n";
echo "// 1. 分发任务\n";
echo "\$dispatcher->dispatch(\$job);\n\n";
echo "// 2. 启动 Worker\n";
echo "\$worker->start();\n\n";
echo "// 3. 查询状态\n";
echo "\$status = \$dispatcher->getJobStatus(\$jobId);\n";
echo "```\n\n";

echo "========================================\n";
echo "✅ 所有异步队列测试完成！\n";
echo "========================================\n";
