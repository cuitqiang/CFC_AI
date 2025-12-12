<?php
declare(strict_types=1);

namespace Services\AI\Providers;

/**
 * 通用中转 API Provider
 * 支持所有中转 API 提供的模型（不做模型过滤）
 */
class RelayProvider extends AbstractProvider
{
    /** @var array 支持的模型列表（可动态扩展） */
    private array $dynamicModels = [];
    
    public function __construct(
        string $apiKey,
        string $baseUrl,
        int $timeout = 60,
        array $defaultOptions = []
    ) {
        parent::__construct($apiKey, $baseUrl, $timeout, $defaultOptions);
    }

    /**
     * 初始化支持的模型 - 中转 API 支持所有常见模型
     */
    protected function initializeSupportedModels(): array
    {
        return array_merge([
            // Deepseek 系列
            'deepseek-chat',
            'deepseek-r1',
            'deepseek-r1-250528',
            'deepseek-reasoner',
            'deepseek-reasoner-all',
            'deepseek-v3',
            'deepseek-v3-250324',
            // Gemini 系列（多模态）
            'gemini-2.0-flash',
            'gemini-1.5-pro',
            'gemini-1.5-flash',
            // OpenAI 系列
            'gpt-4o',
            'gpt-4o-mini',
            'gpt-4-turbo',
            'gpt-4',
            'gpt-3.5-turbo',
            'o1-preview',
            'o1-mini',
            // Claude 系列
            'claude-3-opus',
            'claude-3-sonnet',
            'claude-3-haiku',
        ], $this->dynamicModels);
    }

    /**
     * 动态添加支持的模型
     */
    public function addSupportedModel(string $model): void
    {
        if (!in_array($model, $this->dynamicModels)) {
            $this->dynamicModels[] = $model;
            // 重新初始化
            $this->supportedModels = $this->initializeSupportedModels();
        }
    }

    /**
     * 重写 supports() - 中转 API 接受所有模型（由中转服务器决定是否支持）
     */
    public function supports(string $model): bool
    {
        // 中转 API 尝试支持所有模型，让服务器决定
        return true;
    }

    public function getName(): string
    {
        return 'relay';
    }

    public function chat(array $messages, array $options = []): array
    {
        $options = $this->mergeOptions($options);

        $payload = [
            'model' => $options['model'] ?? 'deepseek-chat',
            'messages' => $messages,
        ];

        // 添加可选参数
        if (isset($options['temperature'])) {
            $payload['temperature'] = $options['temperature'];
        }

        if (isset($options['max_tokens'])) {
            $payload['max_tokens'] = $options['max_tokens'];
        }

        if (isset($options['tools'])) {
            $payload['tools'] = $options['tools'];
        }

        if (isset($options['tool_choice'])) {
            $payload['tool_choice'] = $options['tool_choice'];
        }

        if (isset($options['top_p'])) {
            $payload['top_p'] = $options['top_p'];
        }

        if (isset($options['stream'])) {
            $payload['stream'] = $options['stream'];
        }

        $response = $this->request('/chat/completions', $payload, 'POST');

        return [
            'content' => $response['choices'][0]['message']['content'] ?? '',
            'role' => $response['choices'][0]['message']['role'] ?? 'assistant',
            'tool_calls' => $response['choices'][0]['message']['tool_calls'] ?? null,
            'usage' => $response['usage'] ?? null,
            'model' => $response['model'] ?? $payload['model'],
            'finish_reason' => $response['choices'][0]['finish_reason'] ?? null,
        ];
    }

    public function complete(string $prompt, array $options = []): array
    {
        // 转换为 chat 格式
        return $this->chat([
            ['role' => 'user', 'content' => $prompt]
        ], $options);
    }

    public function embed(string|array $input, array $options = []): array
    {
        $model = $options['model'] ?? 'text-embedding-3-small';

        $payload = [
            'model' => $model,
            'input' => $input,
        ];

        if (isset($options['dimensions'])) {
            $payload['dimensions'] = $options['dimensions'];
        }

        $response = $this->request('POST', '/embeddings', $payload);

        return [
            'embeddings' => array_map(
                fn($item) => $item['embedding'],
                $response['data'] ?? []
            ),
            'usage' => $response['usage'] ?? null,
            'model' => $response['model'] ?? $model,
        ];
    }

    public function stream(array $messages, callable $callback, array $options = []): void
    {
        $options = $this->mergeOptions($options);
        $options['stream'] = true;

        $payload = [
            'model' => $options['model'] ?? 'deepseek-chat',
            'messages' => $messages,
            'stream' => true,
        ];

        if (isset($options['temperature'])) {
            $payload['temperature'] = $options['temperature'];
        }

        if (isset($options['max_tokens'])) {
            $payload['max_tokens'] = $options['max_tokens'];
        }

        $this->streamRequest('/chat/completions', $payload, $callback);
    }

    public function streamChat(array $messages, array $options, callable $callback): void
    {
        $this->stream($messages, $callback, $options);
    }
}
