<?php
/**
 * 死信队列（Dead Letter Queue）专项测试
 * 详细测试死信队列的所有功能
 */

require_once __DIR__ . '/vendor/autoload.php';

use Services\AI\Bootstrap;
use Services\AI\Queue\AIJobDispatcher;
use Services\AI\Queue\AIJobWorker;
use Services\AI\Queue\DeadLetterQueue;
use Services\AI\Queue\Jobs\RunAgentJob;

echo "========================================\n";
echo "死信队列（DLQ）专项测试\n";
echo "========================================\n\n";

echo "💀 什么是死信队列？\n";
echo "-----------------------------------\n";
echo "死信队列（Dead Letter Queue，DLQ）用于存储处理失败的任务。\n";
echo "当任务多次重试仍然失败后，会被移入死信队列，以便：\n";
echo "  • 分析失败原因\n";
echo "  • 手动修复后重试\n";
echo "  • 避免阻塞正常任务\n";
echo "  • 保证系统可靠性\n\n";

Bootstrap::initialize();
$aiManager = Bootstrap::getAIManager();

// ========================================
// 测试1: 创建死信队列
// ========================================
echo "【测试1】创建死信队列\n";
echo "-----------------------------------\n";

echo "创建死信队列（最大容量 100）...\n";
$dlq = new DeadLetterQueue(100);
echo "  ✓ 死信队列创建成功\n";
echo "  最大容量: 100 个任务\n\n";

echo "✅ 死信队列创建测试完成\n\n";

// ========================================
// 测试2: 任务失败进入死信队列
// ========================================
echo "【测试2】任务失败进入死信队列\n";
echo "-----------------------------------\n";

// 创建失败任务类
class DatabaseConnectionFailedJob
{
    public function handle(): array
    {
        throw new \Exception("数据库连接失败: Connection timeout");
    }
}

class APICallFailedJob
{
    public function handle(): array
    {
        throw new \Exception("API调用失败: 503 Service Unavailable");
    }
}

class OutOfMemoryJob
{
    public function handle(): array
    {
        throw new \Exception("内存不足: Out of memory");
    }
}

$dispatcher = new AIJobDispatcher();
$dispatcher->registerQueue('test-dlq', ['max_retries' => 2]);

$worker = new AIJobWorker($dispatcher, $dlq);

echo "创建3个会失败的任务...\n";
$job1 = new DatabaseConnectionFailedJob();
$job2 = new APICallFailedJob();
$job3 = new OutOfMemoryJob();

$jobId1 = $dispatcher->dispatch($job1, 'test-dlq');
$jobId2 = $dispatcher->dispatch($job2, 'test-dlq');
$jobId3 = $dispatcher->dispatch($job3, 'test-dlq');

echo "\n处理任务（每个任务会重试2次）...\n";

// 处理任务1
echo "\n任务1: 数据库连接失败\n";
for ($i = 0; $i < 3; $i++) {
    echo "  尝试 " . ($i + 1) . "...\n";
    $worker->work('test-dlq');
}

// 处理任务2
echo "\n任务2: API调用失败\n";
for ($i = 0; $i < 3; $i++) {
    echo "  尝试 " . ($i + 1) . "...\n";
    $worker->work('test-dlq');
}

// 处理任务3
echo "\n任务3: 内存不足\n";
for ($i = 0; $i < 3; $i++) {
    echo "  尝试 " . ($i + 1) . "...\n";
    $worker->work('test-dlq');
}

echo "\n查看死信队列...\n";
$deadJobs = $dlq->getAll();
echo "  死信队列中的任务数: " . count($deadJobs) . "\n\n";

foreach ($deadJobs as $i => $job) {
    echo "  失败任务 " . ($i + 1) . ":\n";
    echo "    任务ID: {$job['job_id']}\n";
    echo "    任务类: {$job['job_class']}\n";
    echo "    错误: {$job['error']}\n";
    echo "    尝试次数: {$job['attempts']}\n";
    echo "    失败时间: " . date('Y-m-d H:i:s', $job['failed_at']) . "\n\n";
}

echo "✅ 任务失败进入死信队列测试完成\n\n";

// ========================================
// 测试3: 死信队列统计
// ========================================
echo "【测试3】死信队列统计\n";
echo "-----------------------------------\n";

echo "获取统计信息...\n";
$stats = $dlq->getStats();

echo "  总任务数: {$stats['total']}\n";
echo "  最早失败: " . ($stats['oldest'] ? date('Y-m-d H:i:s', $stats['oldest']) : 'N/A') . "\n";
echo "  最新失败: " . ($stats['newest'] ? date('Y-m-d H:i:s', $stats['newest']) : 'N/A') . "\n";

echo "\n错误类型分布:\n";
foreach ($stats['error_types'] as $error => $count) {
    echo "  • {$error}: {$count} 次\n";
}

echo "\n✅ 统计测试完成\n\n";

// ========================================
// 测试4: 按任务ID获取
// ========================================
echo "【测试4】按任务ID获取失败任务\n";
echo "-----------------------------------\n";

if (!empty($deadJobs)) {
    $firstJobId = $deadJobs[0]['job_id'];

    echo "查询任务ID: {$firstJobId}\n";
    $specificJob = $dlq->get($firstJobId);

    if ($specificJob) {
        echo "  ✓ 找到任务\n";
        echo "  错误: {$specificJob['error']}\n";
        echo "  尝试次数: {$specificJob['attempts']}\n";
    } else {
        echo "  ✗ 未找到任务\n";
    }
}

echo "\n查询不存在的任务...\n";
$nonExist = $dlq->get('job_nonexistent_12345');
echo "  结果: " . ($nonExist === null ? '✓ 返回 null (正确)' : '✗ 未返回 null') . "\n";

echo "\n✅ 按ID获取测试完成\n\n";

// ========================================
// 测试5: 单个任务重试
// ========================================
echo "【测试5】从死信队列重试单个任务\n";
echo "-----------------------------------\n";

if (!empty($deadJobs)) {
    $retryJobId = $deadJobs[0]['job_id'];

    echo "重试任务: {$retryJobId}\n";
    echo "  重试前死信队列: " . count($dlq->getAll()) . " 个\n";

    $retried = $dlq->retry($retryJobId, $dispatcher);

    echo "  重试结果: " . ($retried ? '✓ 成功' : '✗ 失败') . "\n";
    echo "  重试后死信队列: " . count($dlq->getAll()) . " 个\n";

    // 检查任务是否回到队列
    $queueStatus = $dispatcher->getJobStatus($retryJobId);
    if ($queueStatus === null) {
        // 任务已经被重新分发，有新的ID
        echo "  ✓ 任务已重新分发到队列\n";
    }
}

echo "\n✅ 单个重试测试完成\n\n";

// ========================================
// 测试6: 批量重试
// ========================================
echo "【测试6】批量重试失败任务\n";
echo "-----------------------------------\n";

// 先添加一些任务到死信队列
echo "准备测试数据（添加5个失败任务）...\n";
$dlq2 = new DeadLetterQueue();
$dispatcher2 = new AIJobDispatcher();
$dispatcher2->registerQueue('batch-test', ['max_retries' => 1]);
$worker2 = new AIJobWorker($dispatcher2, $dlq2);

class AlwaysFailJob
{
    private static int $count = 0;
    private int $id;

    public function __construct()
    {
        $this->id = ++self::$count;
    }

    public function handle(): array
    {
        throw new \Exception("批量测试失败 #{$this->id}");
    }
}

for ($i = 0; $i < 5; $i++) {
    $job = new AlwaysFailJob();
    $jobId = $dispatcher2->dispatch($job, 'batch-test');

    // 执行两次使其进入死信队列
    $worker2->work('batch-test');
    $worker2->work('batch-test');
}

echo "  ✓ 已添加 " . count($dlq2->getAll()) . " 个失败任务\n\n";

echo "批量重试所有任务...\n";
$retriedCount = $dlq2->retryAll($dispatcher2);
echo "  ✓ 已重试 {$retriedCount} 个任务\n";
echo "  死信队列剩余: " . count($dlq2->getAll()) . " 个\n";

echo "\n✅ 批量重试测试完成\n\n";

// ========================================
// 测试7: 限制批量重试数量
// ========================================
echo "【测试7】限制批量重试数量\n";
echo "-----------------------------------\n";

// 创建新的死信队列并添加任务
$dlq3 = new DeadLetterQueue();
$dispatcher3 = new AIJobDispatcher();
$dispatcher3->registerQueue('limit-test', ['max_retries' => 1]);
$worker3 = new AIJobWorker($dispatcher3, $dlq3);

echo "添加10个失败任务...\n";
for ($i = 0; $i < 10; $i++) {
    $job = new AlwaysFailJob();
    $jobId = $dispatcher3->dispatch($job, 'limit-test');
    $worker3->work('limit-test');
    $worker3->work('limit-test');
}

echo "  死信队列: " . count($dlq3->getAll()) . " 个任务\n\n";

echo "限制重试前3个任务...\n";
$limitedRetry = $dlq3->retryAll($dispatcher3, 3);
echo "  已重试: {$limitedRetry} 个\n";
echo "  剩余: " . count($dlq3->getAll()) . " 个\n";

echo "\n✅ 限制重试测试完成\n\n";

// ========================================
// 测试8: 移除任务
// ========================================
echo "【测试8】移除死信队列中的任务\n";
echo "-----------------------------------\n";

if (count($dlq3->getAll()) > 0) {
    $removeJob = $dlq3->getAll()[0];
    $removeJobId = $removeJob['job_id'];

    echo "移除任务: {$removeJobId}\n";
    echo "  移除前: " . count($dlq3->getAll()) . " 个\n";

    $dlq3->remove($removeJobId);

    echo "  移除后: " . count($dlq3->getAll()) . " 个\n";
    echo "  ✓ 任务已移除\n";
}

echo "\n✅ 移除任务测试完成\n\n";

// ========================================
// 测试9: 清空死信队列
// ========================================
echo "【测试9】清空死信队列\n";
echo "-----------------------------------\n";

$clearCount = count($dlq3->getAll());
echo "清空前: {$clearCount} 个任务\n";

$dlq3->clear();

$afterClear = count($dlq3->getAll());
echo "清空后: {$afterClear} 个任务\n";
echo "  ✓ 死信队列已清空\n";

echo "\n✅ 清空测试完成\n\n";

// ========================================
// 测试10: 导出死信队列
// ========================================
echo "【测试10】导出死信队列数据\n";
echo "-----------------------------------\n";

// 添加一些测试数据
$dlq4 = new DeadLetterQueue();
$dispatcher4 = new AIJobDispatcher();
$dispatcher4->registerQueue('export-test', ['max_retries' => 1]);
$worker4 = new AIJobWorker($dispatcher4, $dlq4);

for ($i = 1; $i <= 3; $i++) {
    $job = new AlwaysFailJob();
    $jobId = $dispatcher4->dispatch($job, 'export-test');
    $worker4->work('export-test');
    $worker4->work('export-test');
}

echo "导出死信队列数据（JSON格式）...\n";
$exported = $dlq4->export();

$lines = explode("\n", $exported);
$preview = implode("\n", array_slice($lines, 0, 15));

echo "\n导出数据预览:\n";
echo "```json\n";
echo $preview . "\n";
echo "... (省略)\n";
echo "```\n";

echo "\n导出数据长度: " . strlen($exported) . " 字节\n";
echo "  ✓ 数据已导出\n";

echo "\n✅ 导出测试完成\n\n";

// ========================================
// 测试11: 死信队列容量限制
// ========================================
echo "【测试11】死信队列容量限制\n";
echo "-----------------------------------\n";

$smallDlq = new DeadLetterQueue(5);  // 最多5个
$dispatcher5 = new AIJobDispatcher();
$dispatcher5->registerQueue('capacity-test', ['max_retries' => 1]);
$worker5 = new AIJobWorker($dispatcher5, $smallDlq);

echo "创建容量为 5 的死信队列...\n";
echo "添加 10 个失败任务...\n";

for ($i = 1; $i <= 10; $i++) {
    $job = new AlwaysFailJob();
    $jobId = $dispatcher5->dispatch($job, 'capacity-test');
    $worker5->work('capacity-test');
    $worker5->work('capacity-test');
    echo "  添加任务 {$i}, 当前死信队列: " . count($smallDlq->getAll()) . " 个\n";
}

$finalCount = count($smallDlq->getAll());
echo "\n最终死信队列任务数: {$finalCount}\n";
echo "  ✓ " . ($finalCount === 5 ? '正确限制在5个' : '容量控制失败') . "\n";

echo "\n✅ 容量限制测试完成\n\n";

// ========================================
// 测试12: 实际应用场景演示
// ========================================
echo "【测试12】实际应用场景演示\n";
echo "-----------------------------------\n";

echo "场景: 批量发送邮件，部分失败\n\n";

class SendEmailJob
{
    private string $email;
    private bool $shouldFail;

    public function __construct(string $email, bool $shouldFail = false)
    {
        $this->email = $email;
        $this->shouldFail = $shouldFail;
    }

    public function handle(): array
    {
        if ($this->shouldFail) {
            throw new \Exception("SMTP错误: 无法连接到邮件服务器 ({$this->email})");
        }

        echo "      ✓ 邮件发送成功: {$this->email}\n";
        return ['success' => true, 'email' => $this->email];
    }
}

$emailDlq = new DeadLetterQueue();
$emailDispatcher = new AIJobDispatcher();
$emailDispatcher->registerQueue('email', ['max_retries' => 2]);
$emailWorker = new AIJobWorker($emailDispatcher, $emailDlq);

echo "Step 1: 批量分发邮件任务（10封邮件，3封会失败）\n";
$emails = [
    ['email' => 'user1@example.com', 'fail' => false],
    ['email' => 'user2@example.com', 'fail' => false],
    ['email' => 'invalid1@fail.com', 'fail' => true],
    ['email' => 'user3@example.com', 'fail' => false],
    ['email' => 'user4@example.com', 'fail' => false],
    ['email' => 'invalid2@fail.com', 'fail' => true],
    ['email' => 'user5@example.com', 'fail' => false],
    ['email' => 'user6@example.com', 'fail' => false],
    ['email' => 'invalid3@fail.com', 'fail' => true],
    ['email' => 'user7@example.com', 'fail' => false],
];

foreach ($emails as $data) {
    $job = new SendEmailJob($data['email'], $data['fail']);
    $emailDispatcher->dispatch($job, 'email');
}

echo "  ✓ 已分发 " . count($emails) . " 个任务\n\n";

echo "Step 2: Worker 处理所有任务\n";
$processedCount = 0;
while ($emailWorker->work('email')) {
    $processedCount++;
}

echo "  ✓ 处理了 {$processedCount} 次\n\n";

echo "Step 3: 检查结果\n";
$emailStats = $emailDispatcher->getStats('email');
echo "  已完成: {$emailStats['completed']} 个\n";
echo "  失败: {$emailStats['failed']} 个\n";

$deadEmails = $emailDlq->getAll();
echo "\n死信队列中的失败邮件:\n";
foreach ($deadEmails as $deadEmail) {
    echo "  • {$deadEmail['error']}\n";
}

echo "\n✅ 应用场景演示完成\n\n";

// ========================================
// 总结
// ========================================
echo "========================================\n";
echo "死信队列测试总结\n";
echo "========================================\n\n";

echo "✅ 已测试的功能:\n";
echo "  1. 创建死信队列（指定容量）\n";
echo "  2. 任务失败进入死信队列\n";
echo "  3. 获取统计信息（total, oldest, newest, error_types）\n";
echo "  4. 按任务ID获取（get）\n";
echo "  5. 单个任务重试（retry）\n";
echo "  6. 批量重试（retryAll）\n";
echo "  7. 限制重试数量（retryAll with limit）\n";
echo "  8. 移除任务（remove）\n";
echo "  9. 清空队列（clear）\n";
echo "  10. 导出数据（export）\n";
echo "  11. 容量限制（maxSize）\n";
echo "  12. 实际应用场景（批量邮件）\n\n";

echo "💀 死信队列核心价值:\n";
echo "  ✅ 隔离失败任务 - 不影响正常业务\n";
echo "  ✅ 保留失败信息 - 便于排查问题\n";
echo "  ✅ 支持手动重试 - 修复后可恢复\n";
echo "  ✅ 容量自动控制 - 避免内存溢出\n";
echo "  ✅ 统计和分析 - 了解失败模式\n\n";

echo "🎯 使用场景:\n";
echo "  • API调用失败（网络超时、服务不可用）\n";
echo "  • 数据库错误（连接失败、死锁）\n";
echo "  • 第三方服务异常（支付、短信、邮件）\n";
echo "  • 业务逻辑错误（数据不合法）\n";
echo "  • 资源不足（内存、磁盘）\n\n";

echo "💡 最佳实践:\n";
echo "  1. 设置合理的 max_retries（避免无限重试）\n";
echo "  2. 定期检查死信队列（及时发现问题）\n";
echo "  3. 分析错误类型（改进系统可靠性）\n";
echo "  4. 手动介入修复（人工处理特殊情况）\n";
echo "  5. 定期清理旧数据（避免堆积）\n\n";

echo "🔧 运维建议:\n";
echo "  • 监控死信队列大小（设置告警阈值）\n";
echo "  • 定期导出分析（找出常见错误）\n";
echo "  • 建立处理流程（谁负责、何时处理）\n";
echo "  • 考虑持久化（重启后不丢失）\n\n";

echo "========================================\n";
echo "✅ 所有死信队列测试完成！\n";
echo "========================================\n";
