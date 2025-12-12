<?php
declare(strict_types=1);

namespace Services\AI\Queue;

/**
 * AI 任务调度器
 * 将 AI 任务分发到队列
 */
class AIJobDispatcher
{
    private array $queues = [];
    private string $defaultQueue = 'default';

    /**
     * 注册队列
     *
     * @param string $name 队列名称
     * @param array $config 队列配置
     */
    public function registerQueue(string $name, array $config = []): void
    {
        $this->queues[$name] = array_merge([
            'jobs' => [],
            'max_retries' => 3,
            'retry_delay' => 60,
            'priority' => 0,
        ], $config);
    }

    /**
     * 分发任务到队列
     *
     * @param object $job 任务对象
     * @param string|null $queueName 队列名称
     * @param int $priority 优先级（越大越优先）
     * @return string 任务ID
     */
    public function dispatch(object $job, ?string $queueName = null, int $priority = 0): string
    {
        $queueName = $queueName ?? $this->defaultQueue;

        if (!isset($this->queues[$queueName])) {
            $this->registerQueue($queueName);
        }

        $jobId = $this->generateJobId();

        $jobData = [
            'id' => $jobId,
            'job' => $job,
            'queue' => $queueName,
            'priority' => $priority,
            'attempts' => 0,
            'max_retries' => $this->queues[$queueName]['max_retries'],
            'created_at' => time(),
            'status' => 'pending',
        ];

        $this->queues[$queueName]['jobs'][$jobId] = $jobData;

        $this->log('Job dispatched', [
            'job_id' => $jobId,
            'queue' => $queueName,
            'job_class' => get_class($job),
        ]);

        return $jobId;
    }

    /**
     * 批量分发任务
     *
     * @param array $jobs 任务列表
     * @param string|null $queueName 队列名称
     * @return array 任务ID列表
     */
    public function dispatchBatch(array $jobs, ?string $queueName = null): array
    {
        $jobIds = [];

        foreach ($jobs as $job) {
            $jobIds[] = $this->dispatch($job, $queueName);
        }

        return $jobIds;
    }

    /**
     * 延迟分发任务
     *
     * @param object $job 任务对象
     * @param int $delay 延迟秒数
     * @param string|null $queueName 队列名称
     * @return string 任务ID
     */
    public function dispatchDelayed(object $job, int $delay, ?string $queueName = null): string
    {
        $jobId = $this->dispatch($job, $queueName);

        $queueName = $queueName ?? $this->defaultQueue;
        $this->queues[$queueName]['jobs'][$jobId]['execute_at'] = time() + $delay;
        $this->queues[$queueName]['jobs'][$jobId]['status'] = 'delayed';

        return $jobId;
    }

    /**
     * 获取下一个待执行的任务
     *
     * @param string|null $queueName 队列名称
     * @return array|null 任务数据
     */
    public function getNextJob(?string $queueName = null): ?array
    {
        $queueName = $queueName ?? $this->defaultQueue;

        if (!isset($this->queues[$queueName])) {
            return null;
        }

        $jobs = $this->queues[$queueName]['jobs'];
        $now = time();

        // 过滤出可执行的任务
        $availableJobs = array_filter($jobs, function ($job) use ($now) {
            if ($job['status'] !== 'pending' && $job['status'] !== 'delayed') {
                return false;
            }

            // 检查延迟任务是否到时间
            if ($job['status'] === 'delayed') {
                return isset($job['execute_at']) && $job['execute_at'] <= $now;
            }

            return true;
        });

        if (empty($availableJobs)) {
            return null;
        }

        // 按优先级排序
        uasort($availableJobs, fn($a, $b) => $b['priority'] <=> $a['priority']);

        // 返回第一个任务
        return reset($availableJobs);
    }

    /**
     * 更新任务状态
     *
     * @param string $jobId 任务ID
     * @param string $status 状态
     * @param array $data 额外数据
     */
    public function updateJobStatus(string $jobId, string $status, array $data = []): void
    {
        foreach ($this->queues as $queueName => &$queue) {
            if (isset($queue['jobs'][$jobId])) {
                $queue['jobs'][$jobId]['status'] = $status;
                $queue['jobs'][$jobId] = array_merge($queue['jobs'][$jobId], $data);

                $this->log('Job status updated', [
                    'job_id' => $jobId,
                    'status' => $status,
                ]);

                break;
            }
        }
    }

    /**
     * 获取任务状态
     *
     * @param string $jobId 任务ID
     * @return array|null 任务数据
     */
    public function getJobStatus(string $jobId): ?array
    {
        foreach ($this->queues as $queue) {
            if (isset($queue['jobs'][$jobId])) {
                return $queue['jobs'][$jobId];
            }
        }

        return null;
    }

    /**
     * 获取队列统计
     *
     * @param string|null $queueName 队列名称
     * @return array 统计信息
     */
    public function getStats(?string $queueName = null): array
    {
        if ($queueName !== null) {
            return $this->getQueueStats($queueName);
        }

        $stats = [];

        foreach ($this->queues as $name => $queue) {
            $stats[$name] = $this->getQueueStats($name);
        }

        return $stats;
    }

    /**
     * 获取单个队列的统计
     */
    private function getQueueStats(string $queueName): array
    {
        if (!isset($this->queues[$queueName])) {
            return [];
        }

        $jobs = $this->queues[$queueName]['jobs'];

        return [
            'total' => count($jobs),
            'pending' => count(array_filter($jobs, fn($j) => $j['status'] === 'pending')),
            'processing' => count(array_filter($jobs, fn($j) => $j['status'] === 'processing')),
            'completed' => count(array_filter($jobs, fn($j) => $j['status'] === 'completed')),
            'failed' => count(array_filter($jobs, fn($j) => $j['status'] === 'failed')),
        ];
    }

    /**
     * 生成任务ID
     */
    private function generateJobId(): string
    {
        return uniqid('job_', true);
    }

    /**
     * 记录日志
     */
    private function log(string $message, array $context = []): void
    {
        error_log("[AIJobDispatcher] {$message} " . json_encode($context));
    }

    /**
     * 清理已完成的任务
     *
     * @param int $olderThan 早于此时间戳的任务将被清理
     */
    public function cleanup(int $olderThan): void
    {
        foreach ($this->queues as &$queue) {
            $queue['jobs'] = array_filter($queue['jobs'], function ($job) use ($olderThan) {
                return $job['status'] !== 'completed' || $job['created_at'] > $olderThan;
            });
        }
    }
}
