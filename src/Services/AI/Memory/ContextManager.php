<?php
declare(strict_types=1);

namespace Services\AI\Memory;

/**
 * 上下文管理器
 * 协调短期记忆、摘要和向量存储
 */
class ContextManager
{
    private ShortTerm $shortTerm;
    private Summary $summary;
    private VectorStore $vectorStore;
    private int $maxShortTermMessages;

    public function __construct(
        ShortTerm $shortTerm,
        Summary $summary,
        VectorStore $vectorStore,
        int $maxShortTermMessages = 20
    ) {
        $this->shortTerm = $shortTerm;
        $this->summary = $summary;
        $this->vectorStore = $vectorStore;
        $this->maxShortTermMessages = $maxShortTermMessages;
    }

    /**
     * 加载用户的上下文
     *
     * @param string $userId 用户ID
     * @param string|null $sessionId 会话ID
     * @return array 上下文数据
     */
    public function load(string $userId, ?string $sessionId = null): array
    {
        $context = [
            'short_term' => [],
            'summary' => '',
            'relevant' => [],
        ];

        // 加载短期记忆
        $context['short_term'] = $this->shortTerm->get($userId, $sessionId);

        // 加载摘要
        $context['summary'] = $this->summary->get($userId);

        return $context;
    }

    /**
     * 加载与查询相关的记忆
     *
     * @param string $userId 用户ID
     * @param string $query 查询文本
     * @param int $topK 返回数量
     * @return array 相关记忆
     */
    public function loadRelevant(string $userId, string $query, int $topK = 5): array
    {
        return $this->vectorStore->search($userId, $query, $topK);
    }

    /**
     * 保存对话到记忆
     *
     * @param string $userId 用户ID
     * @param array $messages 消息列表
     * @param string|null $sessionId 会话ID
     */
    public function save(string $userId, array $messages, ?string $sessionId = null): void
    {
        // 保存到短期记忆
        $this->shortTerm->add($userId, $messages, $sessionId);

        // 检查是否需要生成摘要
        $messageCount = $this->shortTerm->count($userId, $sessionId);

        if ($messageCount > $this->maxShortTermMessages) {
            $this->compressToSummary($userId, $sessionId);
        }

        // 提取重要信息到向量存储
        $this->extractImportantInfo($userId, $messages);
    }

    /**
     * 压缩短期记忆为摘要
     */
    private function compressToSummary(string $userId, ?string $sessionId): void
    {
        $messages = $this->shortTerm->get($userId, $sessionId);

        // 生成摘要
        $newSummary = $this->summary->generate($messages);

        // 获取现有摘要
        $existingSummary = $this->summary->get($userId);

        // 合并摘要
        $combinedSummary = $this->summary->merge($existingSummary, $newSummary);

        // 保存摘要
        $this->summary->save($userId, $combinedSummary);

        // 清理旧的短期记忆（保留最近的）
        $this->shortTerm->trim($userId, $sessionId, 10);
    }

    /**
     * 提取重要信息到向量存储
     */
    private function extractImportantInfo(string $userId, array $messages): void
    {
        foreach ($messages as $message) {
            if ($this->isImportant($message)) {
                $this->vectorStore->add($userId, $message['content'], [
                    'role' => $message['role'],
                    'timestamp' => time(),
                ]);
            }
        }
    }

    /**
     * 判断消息是否重要
     */
    private function isImportant(array $message): bool
    {
        // TODO: 实现更智能的判断逻辑
        // 例如：用户偏好、关键决策、重要事实等

        $content = $message['content'] ?? '';

        // 简单判断：包含关键词
        $keywords = ['喜欢', '不喜欢', '需要', '想要', '记住', '重要'];

        foreach ($keywords as $keyword) {
            if (str_contains($content, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 清理用户的所有记忆
     */
    public function clear(string $userId, ?string $sessionId = null): void
    {
        $this->shortTerm->clear($userId, $sessionId);
        $this->summary->clear($userId);
        $this->vectorStore->clear($userId);
    }

    /**
     * 获取记忆统计
     */
    public function getStats(string $userId): array
    {
        return [
            'short_term_count' => $this->shortTerm->count($userId),
            'has_summary' => !empty($this->summary->get($userId)),
            'vector_count' => $this->vectorStore->count($userId),
        ];
    }
}
