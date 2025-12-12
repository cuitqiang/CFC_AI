<?php
declare(strict_types=1);

namespace Services\AI\Queue;

/**
 * AI 任务工作器
 * 从队列中取出任务并执行
 */
class AIJobWorker
{
    private AIJobDispatcher $dispatcher;
    private ?DeadLetterQueue $deadLetterQueue;
    private bool $running = false;
    private int $maxExecutionTime;

    public function __construct(
        AIJobDispatcher $dispatcher,
        ?DeadLetterQueue $deadLetterQueue = null,
        int $maxExecutionTime = 300
    ) {
        $this->dispatcher = $dispatcher;
        $this->deadLetterQueue = $deadLetterQueue;
        $this->maxExecutionTime = $maxExecutionTime;
    }

    /**
     * 启动工作器
     *
     * @param string|null $queueName 队列名称
     * @param int $maxJobs 最大处理任务数（0 = 无限制）
     */
    public function start(?string $queueName = null, int $maxJobs = 0): void
    {
        $this->running = true;
        $processedJobs = 0;

        $this->log('Worker started', ['queue' => $queueName ?? 'default']);

        while ($this->running) {
            $job = $this->dispatcher->getNextJob($queueName);

            if ($job === null) {
                // 没有任务，休眠一秒
                sleep(1);
                continue;
            }

            $this->processJob($job);
            $processedJobs++;

            if ($maxJobs > 0 && $processedJobs >= $maxJobs) {
                $this->log('Max jobs reached, stopping worker');
                break;
            }
        }

        $this->log('Worker stopped', ['processed_jobs' => $processedJobs]);
    }

    /**
     * 处理单个任务
     */
    public function processJob(array $jobData): void
    {
        $jobId = $jobData['id'];
        $job = $jobData['job'];

        $this->log('Processing job', [
            'job_id' => $jobId,
            'job_class' => get_class($job),
        ]);

        // 更新状态为处理中
        $this->dispatcher->updateJobStatus($jobId, 'processing', [
            'started_at' => time(),
        ]);

        $startTime = time();

        try {
            // 设置执行时间限制
            set_time_limit($this->maxExecutionTime);

            // 执行任务
            $result = $job->handle();

            // 标记为完成
            $this->dispatcher->updateJobStatus($jobId, 'completed', [
                'completed_at' => time(),
                'execution_time' => time() - $startTime,
                'result' => $result,
            ]);

            $this->log('Job completed', [
                'job_id' => $jobId,
                'execution_time' => time() - $startTime,
            ]);

        } catch (\Throwable $e) {
            $this->handleJobFailure($jobData, $e);
        }
    }

    /**
     * 处理任务失败
     */
    private function handleJobFailure(array $jobData, \Throwable $exception): void
    {
        $jobId = $jobData['id'];
        $attempts = $jobData['attempts'] + 1;
        $maxRetries = $jobData['max_retries'];

        $this->log('Job failed', [
            'job_id' => $jobId,
            'error' => $exception->getMessage(),
            'attempts' => $attempts,
        ]);

        if ($attempts < $maxRetries) {
            // 重试
            $this->dispatcher->updateJobStatus($jobId, 'pending', [
                'attempts' => $attempts,
                'last_error' => $exception->getMessage(),
                'last_failed_at' => time(),
            ]);

            $this->log('Job will be retried', [
                'job_id' => $jobId,
                'attempt' => $attempts + 1,
            ]);

        } else {
            // 达到最大重试次数，移到死信队列
            $this->dispatcher->updateJobStatus($jobId, 'failed', [
                'attempts' => $attempts,
                'error' => $exception->getMessage(),
                'failed_at' => time(),
            ]);

            if ($this->deadLetterQueue !== null) {
                $this->deadLetterQueue->add($jobData, $exception->getMessage());
            }

            $this->log('Job moved to dead letter queue', [
                'job_id' => $jobId,
            ]);
        }
    }

    /**
     * 停止工作器
     */
    public function stop(): void
    {
        $this->running = false;
        $this->log('Stop signal received');
    }

    /**
     * 处理单次任务（不循环）
     *
     * @param string|null $queueName 队列名称
     * @return bool 是否处理了任务
     */
    public function work(?string $queueName = null): bool
    {
        $job = $this->dispatcher->getNextJob($queueName);

        if ($job === null) {
            return false;
        }

        $this->processJob($job);

        return true;
    }

    /**
     * 记录日志
     */
    private function log(string $message, array $context = []): void
    {
        error_log("[AIJobWorker] {$message} " . json_encode($context));
    }
}
