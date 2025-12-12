<?php
declare(strict_types=1);

namespace Services\AI\Queue;

/**
 * 死信队列
 * 存储失败的任务，用于排查和重试
 */
class DeadLetterQueue
{
    private array $deadJobs = [];
    private int $maxSize;

    public function __construct(int $maxSize = 1000)
    {
        $this->maxSize = $maxSize;
    }

    /**
     * 添加失败的任务
     *
     * @param array $jobData 任务数据
     * @param string $error 错误信息
     */
    public function add(array $jobData, string $error): void
    {
        $deadJob = [
            'job_id' => $jobData['id'],
            'job_class' => get_class($jobData['job']),
            'job_data' => $jobData,
            'error' => $error,
            'failed_at' => time(),
            'attempts' => $jobData['attempts'] ?? 0,
        ];

        $this->deadJobs[] = $deadJob;

        // 限制队列大小
        if (count($this->deadJobs) > $this->maxSize) {
            array_shift($this->deadJobs);
        }

        $this->log('Job added to dead letter queue', [
            'job_id' => $jobData['id'],
            'error' => $error,
        ]);
    }

    /**
     * 获取所有失败的任务
     *
     * @return array 失败任务列表
     */
    public function getAll(): array
    {
        return $this->deadJobs;
    }

    /**
     * 获取指定任务
     *
     * @param string $jobId 任务ID
     * @return array|null 任务数据
     */
    public function get(string $jobId): ?array
    {
        foreach ($this->deadJobs as $job) {
            if ($job['job_id'] === $jobId) {
                return $job;
            }
        }

        return null;
    }

    /**
     * 重试失败的任务
     *
     * @param string $jobId 任务ID
     * @param AIJobDispatcher $dispatcher 调度器
     * @return bool 是否成功重试
     */
    public function retry(string $jobId, AIJobDispatcher $dispatcher): bool
    {
        $deadJob = $this->get($jobId);

        if ($deadJob === null) {
            return false;
        }

        // 从死信队列移除
        $this->remove($jobId);

        // 重新分发任务
        $originalJob = $deadJob['job_data'];
        $dispatcher->dispatch(
            $originalJob['job'],
            $originalJob['queue'],
            $originalJob['priority']
        );

        $this->log('Job retried from dead letter queue', [
            'job_id' => $jobId,
        ]);

        return true;
    }

    /**
     * 批量重试失败的任务
     *
     * @param AIJobDispatcher $dispatcher 调度器
     * @param int $limit 重试数量限制
     * @return int 成功重试的数量
     */
    public function retryAll(AIJobDispatcher $dispatcher, int $limit = 0): int
    {
        $retried = 0;
        $jobs = $this->deadJobs;

        if ($limit > 0) {
            $jobs = array_slice($jobs, 0, $limit);
        }

        foreach ($jobs as $job) {
            if ($this->retry($job['job_id'], $dispatcher)) {
                $retried++;
            }
        }

        $this->log('Batch retry completed', ['retried' => $retried]);

        return $retried;
    }

    /**
     * 移除任务
     *
     * @param string $jobId 任务ID
     */
    public function remove(string $jobId): void
    {
        $this->deadJobs = array_filter(
            $this->deadJobs,
            fn($job) => $job['job_id'] !== $jobId
        );

        $this->deadJobs = array_values($this->deadJobs);
    }

    /**
     * 清空死信队列
     */
    public function clear(): void
    {
        $count = count($this->deadJobs);
        $this->deadJobs = [];

        $this->log('Dead letter queue cleared', ['count' => $count]);
    }

    /**
     * 获取统计信息
     *
     * @return array 统计信息
     */
    public function getStats(): array
    {
        $errorCounts = [];

        foreach ($this->deadJobs as $job) {
            $error = $job['error'];

            if (!isset($errorCounts[$error])) {
                $errorCounts[$error] = 0;
            }

            $errorCounts[$error]++;
        }

        return [
            'total' => count($this->deadJobs),
            'oldest' => !empty($this->deadJobs)
                ? min(array_column($this->deadJobs, 'failed_at'))
                : null,
            'newest' => !empty($this->deadJobs)
                ? max(array_column($this->deadJobs, 'failed_at'))
                : null,
            'error_types' => $errorCounts,
        ];
    }

    /**
     * 导出失败任务（用于分析）
     *
     * @return string JSON 格式
     */
    public function export(): string
    {
        return json_encode($this->deadJobs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * 记录日志
     */
    private function log(string $message, array $context = []): void
    {
        error_log("[DeadLetterQueue] {$message} " . json_encode($context));
    }
}
