<?php
declare(strict_types=1);

namespace Services\AI\Memory;

use Services\AI\Core\RAG\EmbeddingEngine;

/**
 * 向量存储
 * 用于语义搜索和相关记忆检索
 */
class VectorStore
{
    private ?EmbeddingEngine $embeddingEngine;
    private array $storage = [];

    public function __construct(?EmbeddingEngine $embeddingEngine = null)
    {
        $this->embeddingEngine = $embeddingEngine;
    }

    /**
     * 添加文本到向量存储
     *
     * @param string $userId 用户ID
     * @param string $text 文本内容
     * @param array $metadata 元数据
     */
    public function add(string $userId, string $text, array $metadata = []): void
    {
        if ($this->embeddingEngine === null) {
            return;
        }

        try {
            // 生成向量
            $vector = $this->embeddingEngine->embed($text);

            if (!isset($this->storage[$userId])) {
                $this->storage[$userId] = [];
            }

            $this->storage[$userId][] = [
                'text' => $text,
                'vector' => $vector,
                'metadata' => $metadata,
                'timestamp' => time(),
            ];

        } catch (\Throwable $e) {
            // 忽略向量化失败
            error_log("Failed to add to vector store: " . $e->getMessage());
        }
    }

    /**
     * 批量添加
     *
     * @param string $userId 用户ID
     * @param array $items 项目列表 [['text' => ..., 'metadata' => ...], ...]
     */
    public function addBatch(string $userId, array $items): void
    {
        foreach ($items as $item) {
            $this->add($userId, $item['text'], $item['metadata'] ?? []);
        }
    }

    /**
     * 搜索相关内容
     *
     * @param string $userId 用户ID
     * @param string $query 查询文本
     * @param int $topK 返回数量
     * @param float $minSimilarity 最小相似度阈值
     * @return array 相关内容列表
     */
    public function search(string $userId, string $query, int $topK = 5, float $minSimilarity = 0.7): array
    {
        if ($this->embeddingEngine === null) {
            return [];
        }

        if (!isset($this->storage[$userId]) || empty($this->storage[$userId])) {
            return [];
        }

        try {
            // 生成查询向量
            $queryVector = $this->embeddingEngine->embed($query);

            // 计算所有向量的相似度
            $results = [];

            foreach ($this->storage[$userId] as $index => $item) {
                $similarity = $this->embeddingEngine->cosineSimilarity(
                    $queryVector,
                    $item['vector']
                );

                if ($similarity >= $minSimilarity) {
                    $results[] = [
                        'text' => $item['text'],
                        'metadata' => $item['metadata'],
                        'similarity' => $similarity,
                        'timestamp' => $item['timestamp'],
                    ];
                }
            }

            // 按相似度降序排序
            usort($results, fn($a, $b) => $b['similarity'] <=> $a['similarity']);

            // 返回前K个
            return array_slice($results, 0, $topK);

        } catch (\Throwable $e) {
            error_log("Failed to search vector store: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取用户的所有向量数量
     *
     * @param string $userId 用户ID
     * @return int 数量
     */
    public function count(string $userId): int
    {
        return count($this->storage[$userId] ?? []);
    }

    /**
     * 清空用户的向量存储
     *
     * @param string $userId 用户ID
     */
    public function clear(string $userId): void
    {
        unset($this->storage[$userId]);
    }

    /**
     * 删除旧的向量（按时间）
     *
     * @param string $userId 用户ID
     * @param int $olderThan 早于此时间戳的向量将被删除
     */
    public function deleteOld(string $userId, int $olderThan): void
    {
        if (!isset($this->storage[$userId])) {
            return;
        }

        $this->storage[$userId] = array_filter(
            $this->storage[$userId],
            fn($item) => $item['timestamp'] >= $olderThan
        );

        // 重新索引数组
        $this->storage[$userId] = array_values($this->storage[$userId]);
    }

    /**
     * 获取所有向量（用于导出或备份）
     *
     * @param string $userId 用户ID
     * @return array 向量列表
     */
    public function getAll(string $userId): array
    {
        return $this->storage[$userId] ?? [];
    }

    /**
     * 从备份恢复向量
     *
     * @param string $userId 用户ID
     * @param array $vectors 向量列表
     */
    public function restore(string $userId, array $vectors): void
    {
        $this->storage[$userId] = $vectors;
    }

    /**
     * 更新向量的元数据
     *
     * @param string $userId 用户ID
     * @param int $index 向量索引
     * @param array $metadata 新的元数据
     */
    public function updateMetadata(string $userId, int $index, array $metadata): void
    {
        if (isset($this->storage[$userId][$index])) {
            $this->storage[$userId][$index]['metadata'] = array_merge(
                $this->storage[$userId][$index]['metadata'],
                $metadata
            );
        }
    }

    /**
     * 获取存储统计
     *
     * @return array 统计信息
     */
    public function getStats(): array
    {
        $totalUsers = count($this->storage);
        $totalVectors = 0;

        foreach ($this->storage as $vectors) {
            $totalVectors += count($vectors);
        }

        return [
            'total_users' => $totalUsers,
            'total_vectors' => $totalVectors,
            'average_vectors_per_user' => $totalUsers > 0 ? $totalVectors / $totalUsers : 0,
        ];
    }
}
