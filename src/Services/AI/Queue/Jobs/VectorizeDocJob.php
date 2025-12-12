<?php
declare(strict_types=1);

namespace Services\AI\Queue\Jobs;

use Services\AI\Core\RAG\EmbeddingEngine;
use Services\AI\Core\RAG\DocumentChunker;
use Services\AI\Memory\VectorStore;

/**
 * 文档向量化任务
 * 将文档转换为向量并存储
 */
class VectorizeDocJob
{
    private EmbeddingEngine $embeddingEngine;
    private VectorStore $vectorStore;
    private string $userId;
    private string $document;
    private array $metadata;

    public function __construct(
        EmbeddingEngine $embeddingEngine,
        VectorStore $vectorStore,
        string $userId,
        string $document,
        array $metadata = []
    ) {
        $this->embeddingEngine = $embeddingEngine;
        $this->vectorStore = $vectorStore;
        $this->userId = $userId;
        $this->document = $document;
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
            $this->log('Starting document vectorization', [
                'user_id' => $this->userId,
                'document_length' => mb_strlen($this->document),
            ]);

            // 创建文档分块器
            $chunkSize = $this->metadata['chunk_size'] ?? 512;
            $overlap = $this->metadata['overlap'] ?? 50;
            $chunker = new DocumentChunker($chunkSize, $overlap);

            // 分块
            $chunks = $chunker->chunk($this->document);

            $this->log('Document chunked', [
                'chunk_count' => count($chunks),
            ]);

            // 批量向量化
            $vectors = $this->embeddingEngine->batchEmbed($chunks);

            // 存储到向量数据库
            $storedCount = 0;

            foreach ($chunks as $index => $chunk) {
                $chunkMetadata = array_merge($this->metadata, [
                    'chunk_index' => $index,
                    'chunk_size' => mb_strlen($chunk),
                ]);

                $this->vectorStore->add($this->userId, $chunk, $chunkMetadata);
                $storedCount++;
            }

            $this->log('Document vectorization completed', [
                'chunks_stored' => $storedCount,
            ]);

            return [
                'success' => true,
                'chunks_count' => count($chunks),
                'vectors_stored' => $storedCount,
            ];

        } catch (\Throwable $e) {
            $this->log('Document vectorization failed', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
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
            'type' => 'vectorize_doc',
            'user_id' => $this->userId,
            'document_length' => mb_strlen($this->document),
        ];
    }

    /**
     * 记录日志
     */
    private function log(string $message, array $context = []): void
    {
        error_log("[VectorizeDocJob] {$message} " . json_encode($context));
    }
}
