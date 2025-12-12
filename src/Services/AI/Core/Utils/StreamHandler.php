<?php
declare(strict_types=1);

namespace Services\AI\Core\Utils;

/**
 * 流式处理器
 * 处理 AI 模型的流式响应
 */
class StreamHandler
{
    private string $buffer = '';
    private array $chunks = [];

    /**
     * 处理流式数据块
     *
     * @param string $chunk 数据块
     * @return array|null 解析后的数据（如果完整）
     */
    public function handleChunk(string $chunk): ?array
    {
        $this->buffer .= $chunk;
        $this->chunks[] = $chunk;

        // 尝试解析 Server-Sent Events (SSE) 格式
        return $this->parseSseChunk($chunk);
    }

    /**
     * 解析 SSE 格式的数据块
     *
     * @param string $chunk 数据块
     * @return array|null 解析后的数据
     */
    private function parseSseChunk(string $chunk): ?array
    {
        $lines = explode("\n", $chunk);
        $data = null;

        foreach ($lines as $line) {
            $line = trim($line);

            if (empty($line)) {
                continue;
            }

            if (str_starts_with($line, 'data: ')) {
                $jsonData = substr($line, 6);

                if ($jsonData === '[DONE]') {
                    return ['done' => true];
                }

                $decoded = json_decode($jsonData, true);

                if ($decoded !== null) {
                    $data = $decoded;
                }
            }
        }

        return $data;
    }

    /**
     * 从流式响应中提取内容
     *
     * @param array $chunk 数据块
     * @return string 内容
     */
    public function extractContent(array $chunk): string
    {
        return $chunk['choices'][0]['delta']['content'] ?? '';
    }

    /**
     * 从流式响应中提取工具调用
     *
     * @param array $chunk 数据块
     * @return array|null 工具调用
     */
    public function extractToolCalls(array $chunk): ?array
    {
        return $chunk['choices'][0]['delta']['tool_calls'] ?? null;
    }

    /**
     * 检查流是否结束
     *
     * @param array $chunk 数据块
     * @return bool 是否结束
     */
    public function isStreamDone(array $chunk): bool
    {
        return isset($chunk['done']) && $chunk['done'] === true;
    }

    /**
     * 重置缓冲区
     */
    public function reset(): void
    {
        $this->buffer = '';
        $this->chunks = [];
    }

    /**
     * 获取完整内容
     *
     * @return string 完整内容
     */
    public function getFullContent(): string
    {
        $content = '';

        foreach ($this->chunks as $chunk) {
            $parsed = $this->parseSseChunk($chunk);

            if ($parsed !== null && !$this->isStreamDone($parsed)) {
                $content .= $this->extractContent($parsed);
            }
        }

        return $content;
    }

    /**
     * 获取所有数据块
     *
     * @return array 数据块列表
     */
    public function getChunks(): array
    {
        return $this->chunks;
    }

    /**
     * 流式回调包装器
     *
     * @param callable $userCallback 用户回调
     * @return callable 包装后的回调
     */
    public function wrapCallback(callable $userCallback): callable
    {
        return function (array $chunk) use ($userCallback) {
            $content = $this->extractContent($chunk);

            if (!empty($content)) {
                $userCallback($content);
            }

            // 如果有工具调用，也传递给回调
            $toolCalls = $this->extractToolCalls($chunk);

            if ($toolCalls !== null) {
                $userCallback(['tool_calls' => $toolCalls]);
            }

            // 检查是否结束
            if ($this->isStreamDone($chunk)) {
                $userCallback(['done' => true]);
            }
        };
    }

    /**
     * 创建简单的文本流回调
     *
     * @param callable $textCallback 文本回调（接收字符串）
     * @return callable 流式回调
     */
    public static function createTextCallback(callable $textCallback): callable
    {
        return function (array $chunk) use ($textCallback) {
            $handler = new self();
            $content = $handler->extractContent($chunk);

            if (!empty($content)) {
                $textCallback($content);
            }
        };
    }
}
