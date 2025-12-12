<?php
declare(strict_types=1);

namespace Services\AI\Pipeline\Pipes;

use Services\AI\Pipeline\PipelineContext;

/**
 * 工具规划 Pipe
 * 根据用户请求，规划需要使用的工具
 */
class PlanTools
{
    private ?object $toolRegistry;

    public function __construct(?object $toolRegistry = null)
    {
        $this->toolRegistry = $toolRegistry;
    }

    /**
     * 规划并准备工具
     */
    public function __invoke(PipelineContext $context): void
    {
        if ($this->toolRegistry === null) {
            $context->logExecution('plan_tools', 'No tool registry available, skipping');
            return;
        }

        try {
            // 获取所有可用工具
            $availableTools = $this->getAvailableTools();

            // 根据用户输入和上下文，过滤相关工具
            $relevantTools = $this->filterRelevantTools(
                $context->getUserInput(),
                $availableTools,
                $context->getMetadata()
            );

            // 转换为 OpenAI Function Calling 格式
            $toolSchemas = $this->convertToSchemas($relevantTools);

            $context->setTools($toolSchemas);
            $context->setMetadata('available_tool_names', array_keys($relevantTools));

            $context->logExecution(
                'plan_tools',
                'Planned ' . count($relevantTools) . ' tools'
            );

        } catch (\Throwable $e) {
            $context->logExecution('plan_tools', 'Failed to plan tools: ' . $e->getMessage());
        }
    }

    private function getAvailableTools(): array
    {
        // TODO: 在 Phase 4 实现 Tools 模块后，从 ToolRegistry 获取工具
        return [];
    }

    private function filterRelevantTools(string $userInput, array $tools, array $metadata): array
    {
        // TODO: 使用更智能的方式过滤工具（如关键词匹配、向量相似度）
        // 现在简单返回所有工具
        return $tools;
    }

    private function convertToSchemas(array $tools): array
    {
        // TODO: 在 Phase 4 实现 Tools 模块后，转换工具定义为标准 schema
        $schemas = [];

        foreach ($tools as $name => $tool) {
            $schemas[] = [
                'type' => 'function',
                'function' => [
                    'name' => $name,
                    'description' => $tool['description'] ?? '',
                    'parameters' => $tool['parameters'] ?? [
                        'type' => 'object',
                        'properties' => []
                    ]
                ]
            ];
        }

        return $schemas;
    }
}
