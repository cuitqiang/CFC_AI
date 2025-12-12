<?php
declare(strict_types=1);

namespace Services\AI\Pipeline;

/**
 * 管道执行器
 * 按顺序执行各个 Pipe，处理 AI 请求
 */
class Pipeline
{
    private array $pipes = [];

    /**
     * 添加一个 Pipe 到管道
     *
     * @param callable $pipe 接收 PipelineContext 并返回 void 的函数
     */
    public function pipe(callable $pipe): self
    {
        $this->pipes[] = $pipe;
        return $this;
    }

    /**
     * 批量添加多个 Pipe
     *
     * @param array $pipes Pipe 数组
     */
    public function through(array $pipes): self
    {
        foreach ($pipes as $pipe) {
            $this->pipe($pipe);
        }
        return $this;
    }

    /**
     * 执行管道，依次通过所有 Pipe
     *
     * @param PipelineContext $context 管道上下文
     * @return PipelineContext 处理后的上下文
     */
    public function process(PipelineContext $context): PipelineContext
    {
        foreach ($this->pipes as $index => $pipe) {
            if (!$context->shouldContinue()) {
                $context->logExecution(
                    'pipeline_stopped',
                    "Pipeline stopped at pipe #{$index}"
                );
                break;
            }

            try {
                $pipe($context);
            } catch (\Throwable $e) {
                $context->stop("Error in pipe #{$index}: " . $e->getMessage());
                $context->logExecution(
                    'pipe_error',
                    "Pipe #{$index} failed: " . $e->getMessage()
                );
                break;
            }
        }

        return $context;
    }

    /**
     * 重置管道，清空所有 Pipe
     */
    public function reset(): void
    {
        $this->pipes = [];
    }

    /**
     * 获取当前管道中的 Pipe 数量
     */
    public function getPipeCount(): int
    {
        return count($this->pipes);
    }
}
