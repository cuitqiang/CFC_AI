<?php
declare(strict_types=1);

namespace Services\AI\Pipeline\Pipes;

use Services\AI\Pipeline\PipelineContext;

/**
 * 保存记忆 Pipe
 * 将对话保存到记忆系统
 */
class SaveMemory
{
    private ?object $contextManager;

    public function __construct(?object $contextManager = null)
    {
        $this->contextManager = $contextManager;
    }

    /**
     * 保存对话记忆
     */
    public function __invoke(PipelineContext $context): void
    {
        if ($this->contextManager === null) {
            $context->logExecution('save_memory', 'No context manager available, skipping');
            return;
        }

        try {
            $userId = $context->getMetadataValue('user_id', 'anonymous');
            $sessionId = $context->getMetadataValue('session_id', null);

            $messages = $context->getMessages();
            $modelResponse = $context->getModelResponse();

            // 保存短期记忆（完整对话）
            $this->saveShortTermMemory($userId, $sessionId, $messages);

            // 检查是否需要生成摘要
            if ($this->shouldGenerateSummary($messages)) {
                $summary = $this->generateSummary($messages);
                $this->saveLongTermSummary($userId, $summary);
                $context->logExecution('save_memory', 'Generated and saved summary');
            }

            // 如果有重要信息，存入向量数据库
            if ($this->hasImportantInfo($messages)) {
                $this->saveToVectorStore($userId, $messages);
                $context->logExecution('save_memory', 'Saved to vector store');
            }

            $context->logExecution('save_memory', 'Memory saved successfully');

        } catch (\Throwable $e) {
            $context->logExecution('save_memory', 'Failed to save memory: ' . $e->getMessage());
        }
    }

    private function saveShortTermMemory(string $userId, ?string $sessionId, array $messages): void
    {
        // TODO: 在 Phase 6 实现 Memory 模块后，保存短期记忆
    }

    private function shouldGenerateSummary(array $messages): bool
    {
        // 如果对话超过 20 条消息，生成摘要
        return count($messages) > 20;
    }

    private function generateSummary(array $messages): string
    {
        // TODO: 在 Phase 6 实现 Memory 模块后，使用模型生成对话摘要
        return '';
    }

    private function saveLongTermSummary(string $userId, string $summary): void
    {
        // TODO: 在 Phase 6 实现 Memory 模块后，保存长期摘要
    }

    private function hasImportantInfo(array $messages): bool
    {
        // TODO: 实现逻辑判断是否包含重要信息（如用户偏好、关键决策等）
        return false;
    }

    private function saveToVectorStore(string $userId, array $messages): void
    {
        // TODO: 在 Phase 6 实现 Memory 模块后，将重要信息存入向量数据库
    }
}
