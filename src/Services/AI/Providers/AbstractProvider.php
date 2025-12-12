<?php
declare(strict_types=1);

namespace Services\AI\Providers;

/**
 * AI 提供者抽象基类
 * 提供通用功能和默认实现
 */
abstract class AbstractProvider implements ProviderInterface
{
    protected string $apiKey;
    protected string $baseUrl;
    protected int $timeout;
    protected array $defaultOptions;
    protected array $supportedModels;

    public function __construct(
        string $apiKey,
        string $baseUrl,
        int $timeout = 30,
        array $defaultOptions = []
    ) {
        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->timeout = $timeout;
        $this->defaultOptions = $defaultOptions;
        $this->supportedModels = $this->initializeSupportedModels();
    }

    /**
     * 初始化支持的模型列表（子类实现）
     */
    abstract protected function initializeSupportedModels(): array;

    public function getSupportedModels(): array
    {
        return $this->supportedModels;
    }

    public function supportsModel(string $model): bool
    {
        return in_array($model, $this->supportedModels, true);
    }

    /**
     * 发送 HTTP 请求
     *
     * @param string $endpoint API 端点
     * @param array $data 请求数据
     * @param string $method HTTP 方法
     * @return array 响应数据
     */
    protected function request(string $endpoint, array $data, string $method = 'POST'): array
    {
        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey,
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \RuntimeException("请求失败: {$error}");
        }

        if ($httpCode !== 200) {
            throw new \RuntimeException("HTTP 错误: {$httpCode}, 响应: {$response}");
        }

        $decoded = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("JSON 解析失败: " . json_last_error_msg());
        }

        return $decoded;
    }

    /**
     * 流式请求
     *
     * @param string $endpoint API 端点
     * @param array $data 请求数据
     * @param callable $callback 流式回调
     */
    protected function streamRequest(string $endpoint, array $data, callable $callback): void
    {
        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey,
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $chunk) use ($callback) {
            $lines = explode("\n", $chunk);

            foreach ($lines as $line) {
                $line = trim($line);

                if (empty($line) || !str_starts_with($line, 'data: ')) {
                    continue;
                }

                $data = substr($line, 6);

                if ($data === '[DONE]') {
                    continue;
                }

                $decoded = json_decode($data, true);

                if ($decoded !== null) {
                    $callback($decoded);
                }
            }

            return strlen($chunk);
        });

        curl_exec($ch);
        curl_close($ch);
    }

    /**
     * 合并选项
     */
    protected function mergeOptions(array $options): array
    {
        return array_merge($this->defaultOptions, $options);
    }

    public function countTokens(array $messages): int
    {
        // 简单估算：每个字符约 0.25 token（中文）或 0.33 token（英文）
        // 实际应使用 tiktoken 或提供者的 tokenizer
        $totalChars = 0;

        foreach ($messages as $message) {
            $content = $message['content'] ?? '';
            $totalChars += mb_strlen($content);
        }

        return (int) ceil($totalChars * 0.5);
    }
}
