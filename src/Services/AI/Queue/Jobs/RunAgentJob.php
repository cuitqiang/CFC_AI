<?php
declare(strict_types=1);

namespace Services\AI\Queue\Jobs;

use Services\AI\Core\AIManager;

/**
 * 运行 AI Agent 任务
 * 异步执行 AI 代理任务
 */
class RunAgentJob
{
    private AIManager $aiManager;
    private string $userInput;
    private array $metadata;

    public function __construct(
        AIManager $aiManager,
        string $userInput,
        array $metadata = []
    ) {
        $this->aiManager = $aiManager;
        $this->userInput = $userInput;
        $this->metadata = $metadata;
    }

    /**
     * 执行任务
     *
     * @return array 执行结果
     */
    public function handle(): array
    {
        try {
            $this->log('Starting AI agent execution', [
                'user_id' => $this->metadata['user_id'] ?? 'anonymous',
            ]);

            // 执行 AI 处理
            $result = $this->aiManager->process($this->userInput, $this->metadata);

            $this->log('AI agent execution completed', [
                'success' => $result['success'],
            ]);

            // 如果配置了回调，执行回调
            if (isset($this->metadata['callback'])) {
                $this->executeCallback($result);
            }

            return $result;

        } catch (\Throwable $e) {
            $this->log('AI agent execution failed', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * 执行回调
     */
    private function executeCallback(array $result): void
    {
        $callback = $this->metadata['callback'];

        if (is_callable($callback)) {
            try {
                $callback($result);
            } catch (\Throwable $e) {
                $this->log('Callback execution failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * 获取任务信息
     *
     * @return array 任务信息
     */
    public function getInfo(): array
    {
        return [
            'type' => 'run_agent',
            'user_id' => $this->metadata['user_id'] ?? 'anonymous',
            'input_length' => mb_strlen($this->userInput),
        ];
    }

    /**
     * 记录日志
     */
    private function log(string $message, array $context = []): void
    {
        error_log("[RunAgentJob] {$message} " . json_encode($context));
    }
}
