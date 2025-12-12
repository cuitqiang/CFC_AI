<?php
declare(strict_types=1);

namespace Services\AI\Providers;

/**
 * Embedding 提供者
 * 用于文本向量化（RAG、语义搜索）
 */
class EmbeddingProvider extends AbstractProvider
{
    public function __construct(
        string $apiKey,
        string $baseUrl = 'https://api.openai.com/v1',
        int $timeout = 30,
        array $defaultOptions = []
    ) {
        parent::__construct($apiKey, $baseUrl, $timeout, $defaultOptions);
    }

    protected function initializeSupportedModels(): array
    {
        return [
            'text-embedding-3-small',
            'text-embedding-3-large',
            'text-embedding-ada-002',
        ];
    }

    public function getName(): string
    {
        return 'embedding';
    }

    /**
     * 生成文本向量
     *
     * @param string|array $input 单个文本或文本数组
     * @param array $options 选项（model等）
     * @return array 向量数据
     */
    public function embed(string|array $input, array $options = []): array
    {
        $options = $this->mergeOptions($options);

        $payload = [
            'model' => $options['model'] ?? 'text-embedding-3-small',
            'input' => $input,
        ];

        if (isset($options['encoding_format'])) {
            $payload['encoding_format'] = $options['encoding_format'];
        }

        if (isset($options['dimensions'])) {
            $payload['dimensions'] = $options['dimensions'];
        }

        return $this->request('embeddings', $payload);
    }

    /**
     * 批量生成向量
     *
     * @param array $texts 文本数组
     * @param array $options 选项
     * @return array 向量数组
     */
    public function batchEmbed(array $texts, array $options = []): array
    {
        $response = $this->embed($texts, $options);

        $embeddings = [];
        foreach ($response['data'] as $item) {
            $embeddings[] = $item['embedding'];
        }

        return $embeddings;
    }

    public function chat(array $messages, array $options = []): array
    {
        throw new \BadMethodCallException('Embedding provider does not support chat');
    }

    public function streamChat(array $messages, array $options, callable $callback): void
    {
        throw new \BadMethodCallException('Embedding provider does not support streaming chat');
    }

    public function countTokens(array $messages): int
    {
        // Embedding 模型的 token 计算与聊天模型类似
        $totalChars = 0;

        foreach ($messages as $message) {
            if (is_string($message)) {
                $totalChars += mb_strlen($message);
            } elseif (is_array($message) && isset($message['content'])) {
                $totalChars += mb_strlen($message['content']);
            }
        }

        return (int) ceil($totalChars * 0.5);
    }
}
