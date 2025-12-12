<?php
declare(strict_types=1);

namespace Services\AI\Core\RAG;

use Services\AI\Providers\EmbeddingProvider;

/**
 * 向量化引擎
 * 将文本转换为向量用于语义搜索
 */
class EmbeddingEngine
{
    private EmbeddingProvider $provider;
    private string $model;
    private int $chunkSize;
    private int $dimensions;

    public function __construct(
        EmbeddingProvider $provider,
        string $model = 'text-embedding-3-small',
        int $chunkSize = 512,
        int $dimensions = 1536
    ) {
        $this->provider = $provider;
        $this->model = $model;
        $this->chunkSize = $chunkSize;
        $this->dimensions = $dimensions;
    }

    /**
     * 将单个文本转换为向量
     *
     * @param string $text 文本内容
     * @return array 向量数组
     */
    public function embed(string $text): array
    {
        $response = $this->provider->embed($text, [
            'model' => $this->model,
            'dimensions' => $this->dimensions,
        ]);

        return $response['data'][0]['embedding'] ?? [];
    }

    /**
     * 批量将文本转换为向量
     *
     * @param array $texts 文本数组
     * @return array 向量数组
     */
    public function batchEmbed(array $texts): array
    {
        return $this->provider->batchEmbed($texts, [
            'model' => $this->model,
            'dimensions' => $this->dimensions,
        ]);
    }

    /**
     * 将文档转换为向量（自动分块）
     *
     * @param string $document 文档内容
     * @param DocumentChunker|null $chunker 分块器
     * @return array 向量数组（每个分块对应一个向量）
     */
    public function embedDocument(string $document, ?DocumentChunker $chunker = null): array
    {
        if ($chunker === null) {
            $chunker = new DocumentChunker($this->chunkSize);
        }

        // 分块
        $chunks = $chunker->chunk($document);

        // 批量向量化
        return $this->batchEmbed($chunks);
    }

    /**
     * 计算两个向量的余弦相似度
     *
     * @param array $vec1 向量1
     * @param array $vec2 向量2
     * @return float 相似度（0-1）
     */
    public function cosineSimilarity(array $vec1, array $vec2): float
    {
        if (count($vec1) !== count($vec2)) {
            throw new \InvalidArgumentException('向量维度不匹配');
        }

        $dotProduct = 0.0;
        $norm1 = 0.0;
        $norm2 = 0.0;

        for ($i = 0; $i < count($vec1); $i++) {
            $dotProduct += $vec1[$i] * $vec2[$i];
            $norm1 += $vec1[$i] * $vec1[$i];
            $norm2 += $vec2[$i] * $vec2[$i];
        }

        $norm1 = sqrt($norm1);
        $norm2 = sqrt($norm2);

        if ($norm1 == 0.0 || $norm2 == 0.0) {
            return 0.0;
        }

        return $dotProduct / ($norm1 * $norm2);
    }

    /**
     * 在向量列表中查找最相似的
     *
     * @param array $queryVector 查询向量
     * @param array $vectors 候选向量列表
     * @param int $topK 返回前K个
     * @return array 最相似的向量索引和相似度
     */
    public function findMostSimilar(array $queryVector, array $vectors, int $topK = 5): array
    {
        $similarities = [];

        foreach ($vectors as $index => $vector) {
            $similarity = $this->cosineSimilarity($queryVector, $vector);
            $similarities[] = [
                'index' => $index,
                'similarity' => $similarity,
            ];
        }

        // 按相似度降序排序
        usort($similarities, fn($a, $b) => $b['similarity'] <=> $a['similarity']);

        // 返回前K个
        return array_slice($similarities, 0, $topK);
    }

    /**
     * 设置模型
     */
    public function setModel(string $model): void
    {
        $this->model = $model;
    }

    /**
     * 设置维度
     */
    public function setDimensions(int $dimensions): void
    {
        $this->dimensions = $dimensions;
    }
}
