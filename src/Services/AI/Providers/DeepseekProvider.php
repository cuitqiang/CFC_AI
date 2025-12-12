<?php
declare(strict_types=1);

namespace Services\AI\Providers;

/**
 * Deepseek AI 提供者
 * 对接 Deepseek API
 */
class DeepseekProvider extends AbstractProvider
{
    public function __construct(
        string $apiKey,
        string $baseUrl = 'https://api.deepseek.com/v1',
        int $timeout = 30,
        array $defaultOptions = []
    ) {
        parent::__construct($apiKey, $baseUrl, $timeout, $defaultOptions);
    }

    protected function initializeSupportedModels(): array
    {
        return [
            'deepseek-chat',
            'deepseek-coder',
            'deepseek-reasoner',
            'deepseek-v3',
        ];
    }

    public function getName(): string
    {
        return 'deepseek';
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

        if (isset($options['frequency_penalty'])) {
            $payload['frequency_penalty'] = $options['frequency_penalty'];
        }

        if (isset($options['presence_penalty'])) {
            $payload['presence_penalty'] = $options['presence_penalty'];
        }

        return $this->request('chat/completions', $payload);
    }

    public function streamChat(array $messages, array $options, callable $callback): void
    {
        $options = $this->mergeOptions($options);

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

        if (isset($options['tools'])) {
            $payload['tools'] = $options['tools'];
        }

        $this->streamRequest('chat/completions', $payload, $callback);
    }
}
