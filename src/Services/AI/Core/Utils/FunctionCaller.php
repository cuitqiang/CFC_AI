<?php
declare(strict_types=1);

namespace Services\AI\Core\Utils;

/**
 * 函数调用工具
 * 处理模型的 Function Calling
 */
class FunctionCaller
{
    /**
     * 解析工具调用
     *
     * @param array $modelResponse 模型响应
     * @return array 工具调用列表
     */
    public function parseToolCalls(array $modelResponse): array
    {
        $message = $modelResponse['choices'][0]['message'] ?? [];

        if (!isset($message['tool_calls'])) {
            return [];
        }

        return $message['tool_calls'];
    }

    /**
     * 检查是否包含工具调用
     *
     * @param array $modelResponse 模型响应
     * @return bool 是否包含工具调用
     */
    public function hasToolCalls(array $modelResponse): bool
    {
        return !empty($this->parseToolCalls($modelResponse));
    }

    /**
     * 构建工具调用结果消息
     *
     * @param string $toolCallId 工具调用ID
     * @param string $toolName 工具名称
     * @param array $result 执行结果
     * @return array 消息数组
     */
    public function buildToolResultMessage(string $toolCallId, string $toolName, array $result): array
    {
        return [
            'tool_call_id' => $toolCallId,
            'role' => 'tool',
            'name' => $toolName,
            'content' => json_encode($result, JSON_UNESCAPED_UNICODE),
        ];
    }

    /**
     * 从工具调用中提取参数
     *
     * @param array $toolCall 工具调用
     * @return array 参数数组
     */
    public function extractArguments(array $toolCall): array
    {
        $argumentsJson = $toolCall['function']['arguments'] ?? '{}';

        $arguments = json_decode($argumentsJson, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('无法解析工具参数: ' . json_last_error_msg());
        }

        return $arguments;
    }

    /**
     * 验证工具调用格式
     *
     * @param array $toolCall 工具调用
     * @return bool 格式是否有效
     */
    public function validateToolCall(array $toolCall): bool
    {
        return isset($toolCall['id'])
            && isset($toolCall['type'])
            && isset($toolCall['function']['name'])
            && isset($toolCall['function']['arguments']);
    }

    /**
     * 批量解析工具调用
     *
     * @param array $modelResponse 模型响应
     * @return array 解析后的工具调用列表
     */
    public function parseAllToolCalls(array $modelResponse): array
    {
        $toolCalls = $this->parseToolCalls($modelResponse);
        $parsed = [];

        foreach ($toolCalls as $toolCall) {
            if (!$this->validateToolCall($toolCall)) {
                continue;
            }

            try {
                $parsed[] = [
                    'id' => $toolCall['id'],
                    'name' => $toolCall['function']['name'],
                    'arguments' => $this->extractArguments($toolCall),
                ];
            } catch (\Throwable $e) {
                // 跳过无效的工具调用
                continue;
            }
        }

        return $parsed;
    }

    /**
     * 构建带工具的消息列表
     *
     * @param array $messages 原始消息列表
     * @param array $tools 工具定义
     * @return array 包含工具的消息列表
     */
    public function buildMessagesWithTools(array $messages, array $tools): array
    {
        // OpenAI/Deepseek 不需要在消息中包含工具定义
        // 工具定义在请求的 tools 字段中
        return $messages;
    }

    /**
     * 格式化工具定义（确保符合 OpenAI 格式）
     *
     * @param array $tools 工具列表
     * @return array 格式化后的工具定义
     */
    public function formatToolDefinitions(array $tools): array
    {
        $formatted = [];

        foreach ($tools as $tool) {
            if (isset($tool['type']) && $tool['type'] === 'function') {
                $formatted[] = $tool;
            } elseif (isset($tool['function'])) {
                $formatted[] = [
                    'type' => 'function',
                    'function' => $tool['function'],
                ];
            }
        }

        return $formatted;
    }
}
