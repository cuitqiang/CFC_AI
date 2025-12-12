<?php
declare(strict_types=1);

namespace Services\AI\Pipeline\Pipes;

use Services\AI\Pipeline\PipelineContext;

/**
 * 执行工具 Pipe
 * 执行模型请求的工具调用
 */
class ExecuteTool
{
    private ?object $toolRegistry;
    private ?object $toolSandbox;
    private int $maxIterations;

    public function __construct(
        ?object $toolRegistry = null,
        ?object $toolSandbox = null,
        int $maxIterations = 5
    ) {
        $this->toolRegistry = $toolRegistry;
        $this->toolSandbox = $toolSandbox;
        $this->maxIterations = $maxIterations;
    }

    /**
     * 执行工具调用
     */
    public function __invoke(PipelineContext $context): void
    {
        $hasToolCalls = $context->getMetadataValue('has_tool_calls', false);

        if (!$hasToolCalls) {
            $context->logExecution('execute_tool', 'No tool calls to execute');
            return;
        }

        if ($this->toolRegistry === null) {
            $context->logExecution('execute_tool', 'Tool registry not available');
            return;
        }

        $iteration = $context->getMetadataValue('tool_iteration', 0);

        if ($iteration >= $this->maxIterations) {
            $context->stop('达到最大工具调用迭代次数：' . $this->maxIterations);
            $context->logExecution('execute_tool', 'Max iterations reached');
            return;
        }

        try {
            $toolCalls = $context->getMetadataValue('tool_calls', []);
            $results = [];

            foreach ($toolCalls as $toolCall) {
                $toolName = $toolCall['function']['name'];
                $arguments = json_decode($toolCall['function']['arguments'], true);

                $context->logExecution('execute_tool', "Executing tool: {$toolName}");

                $result = $this->executeTool($toolName, $arguments);

                $results[] = [
                    'tool_call_id' => $toolCall['id'],
                    'role' => 'tool',
                    'name' => $toolName,
                    'content' => json_encode($result)
                ];
            }

            $context->setToolResults($results);

            // 将工具结果添加到消息历史
            $messages = $context->getMessages();

            // 添加 assistant 的工具调用消息
            $modelResponse = $context->getModelResponse();
            $messages[] = $modelResponse['choices'][0]['message'];

            // 添加工具执行结果
            foreach ($results as $result) {
                $messages[] = $result;
            }

            $context->setMessages($messages);

            // 增加迭代计数，准备下一轮调用
            $context->setMetadata('tool_iteration', $iteration + 1);
            $context->setMetadata('has_tool_calls', false);

            $context->logExecution('execute_tool', 'Executed ' . count($results) . ' tools');

        } catch (\Throwable $e) {
            $context->logExecution('execute_tool', 'Tool execution failed: ' . $e->getMessage());
        }
    }

    private function executeTool(string $toolName, array $arguments): array
    {
        // TODO: 在 Phase 4 实现 Tools 模块后，从 ToolRegistry 获取并执行工具
        // 如果配置了沙箱，应在沙箱中执行

        return [
            'success' => false,
            'message' => 'Tool execution will be implemented in Phase 4',
            'tool' => $toolName,
            'arguments' => $arguments
        ];
    }
}
