<?php
declare(strict_types=1);

namespace Services\AI\Pipeline\Pipes;

use Services\AI\Pipeline\PipelineContext;

/**
 * 加载记忆 Pipe
 * 从记忆系统加载上下文和历史对话
 */
class LoadMemory
{
    private ?object $contextManager;

    public function __construct(?object $contextManager = null)
    {
        $this->contextManager = $contextManager;
    }

    /**
     * 加载用户的对话记忆
     */
    public function __invoke(PipelineContext $context): void
    {
        $userId = $context->getMetadataValue('user_id', 'anonymous');
        $sessionId = $context->getMetadataValue('session_id', null);

        if ($this->contextManager === null) {
            $context->logExecution('load_memory', 'No context manager available, skipping');
            return;
        }

        try {
            // 加载短期记忆（最近的对话）
            $shortTermMemory = $this->loadShortTermMemory($userId, $sessionId);

            // 加载长期记忆摘要
            $longTermSummary = $this->loadLongTermSummary($userId);

            // 加载向量检索的相关记忆
            $relevantMemories = $this->loadRelevantMemories(
                $context->getUserInput(),
                $userId
            );

            $memory = [
                'short_term' => $shortTermMemory,
                'long_term_summary' => $longTermSummary,
                'relevant' => $relevantMemories,
            ];

            $context->setMemory($memory);

            // 将记忆注入到消息列表中（作为系统消息）
            if (!empty($longTermSummary)) {
                $messages = $context->getMessages();
                array_unshift($messages, [
                    'role' => 'system',
                    'content' => '历史上下文：' . $longTermSummary
                ]);
                $context->setMessages($messages);
            }

            $context->logExecution('load_memory', 'Memory loaded successfully');

        } catch (\Throwable $e) {
            $context->logExecution('load_memory', 'Failed to load memory: ' . $e->getMessage());
        }
    }

    private function loadShortTermMemory(string $userId, ?string $sessionId): array
    {
        // TODO: 在 Phase 6 实现 Memory 模块后，这里将调用真实的记忆加载逻辑
        return [];
    }

    private function loadLongTermSummary(string $userId): string
    {
        // TODO: 在 Phase 6 实现 Memory 模块后，加载长期记忆摘要
        return '';
    }

    private function loadRelevantMemories(string $query, string $userId): array
    {
        // TODO: 在 Phase 6 实现 Memory 模块后，使用向量检索加载相关记忆
        return [];
    }
}
